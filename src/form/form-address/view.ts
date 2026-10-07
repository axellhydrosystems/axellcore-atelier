import {
	store,
	getContext,
	getElement,
	withScope,
} from '@wordpress/interactivity';
import { STATES_BY_COUNTRY } from './states';

interface Option {
	label: string;
	value: string;
}

/**
 * Context of one address control (its own region). The links name the fields
 * it follows; the values themselves live in the store state, shared by every
 * control of the same form.
 */
interface ControlContext {
	/** Id of the form the control belongs to (data-form-id), set on load. */
	form: string;
	/** Country chosen in the block settings ("Seleção"); empty = from the field. */
	fixedCountry?: string;
	countryField?: string;
	stateField?: string;
	/** UF: the control's id, bound to its active field (select or text). */
	fieldId?: string;
	selectId?: string | null;
	textId?: string | null;
	/** Phone: line type (Brazil only): mobile or landline; absent = both. */
	lineType?: string;
	/** City search: typed text, picked city name, list open, active option. */
	query?: string;
	/** City search: what was typed (filters the list; query shows the highlighted city). */
	typed?: string;
	code?: string;
	open?: boolean;
	active?: number;
}

interface AddressState {
	citiesUrl: string;
	/** Countries whose cities come from the cities endpoint (filterable in PHP). */
	cityCountries: string[];
	/** Field values, by "form|name". */
	values: Record< string, string >;
	/** Cities loaded for each state field, by "form|name". */
	cities: Record< string, Option[] >;
}

const MAX_CITIES = 20;

/** Key of a field: its form (data-form-id) and its name. */
const keyOf = ( el: Element | null, name: string ) => {
	const form = el?.closest( 'form' );
	return `${ form?.getAttribute( 'data-form-id' ) || '' }|${ name }`;
};

/** Element of the directive being evaluated. */
const here = () => getElement().ref as HTMLElement | null;

/** Lower case, no accents, for matching typed city names. */
const fold = ( text: string ) =>
	text.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase();

/**
 * A city name as a slug, for matching what was typed in full: no case,
 * accents, hyphens or apostrophes ("sao joao del rei" = "São João del-Rei").
 *
 * @param text City name.
 */
const slug = ( text: string ) =>
	fold( text )
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );

/** Country of a control: chosen in its settings, or the value of its country field. */
function countryOf( context: ControlContext ): string {
	// Codes in upper case, whatever the field or the block stored (br = BR).
	if ( context.fixedCountry !== undefined ) {
		return context.fixedCountry.toUpperCase();
	}
	const s = state as unknown as AddressState;
	return ( s.values[ `${ context.form }|${ context.countryField || 'country' }` ] || '' ).toUpperCase();
}

const { state } = store( 'axell/address', {
	state: {
		values: {},
		cities: {},
		/** Country of the control being evaluated (its countryField). */
		get country(): string {
			return countryOf( getContext< ControlContext >() );
		},
		/** The state control shows a list (the country has one). */
		get hasStateList(): boolean {
			return !! STATES_BY_COUNTRY[ ( state as unknown as { country: string } ).country ];
		},
		/** The city control shows a list or a search (the country has cities). */
		get hasCityList(): boolean {
			const s = state as unknown as AddressState & { country: string };
			return !! s.country && ( s.cityCountries || [] ).includes( s.country );
		},
		get cityListOpen(): boolean {
			const context = getContext< ControlContext >();
			return !! context.open && ( context.typed ?? context.query ?? '' ).trim() !== '';
		},
	},
	actions: {
		/** Keep the value of a linked field (country, free-text state or city). */
		onField( event: Event ) {
			const field = event.target as HTMLInputElement | HTMLSelectElement;
			( state as unknown as AddressState ).values[ keyOf( field, field.name ) ] = field.value;
		},

		*onState( event: Event ): Generator< unknown, void, unknown > {
			const s = state as unknown as AddressState;
			const select = event.target as HTMLSelectElement;
			const key = keyOf( select, select.name );
			const uf = select.value;
			s.values[ key ] = uf;
			s.cities[ key ] = [];

			const country = countryOf( getContext< ControlContext >() );
			if ( ! uf || ! ( s.cityCountries || [] ).includes( country ) ) {
				return;
			}

			// Cities of the state: the same REST route the form already uses.
			const url = new URL( s.citiesUrl );
			url.searchParams.set( 'uf', uf );
			url.searchParams.set( 'country', country );
			try {
				const response = ( yield fetch( url.toString() ) ) as Response;
				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}
				const cities = ( yield response.json() ) as Option[];
				if ( s.values[ key ] === uf ) {
					// The form sends (and members store) the city name, not its IBGE code.
					s.cities[ key ] = cities.map( ( city ) => ( { value: city.label, label: city.label } ) );
				}
			} catch {
				s.cities[ key ] = [];
			}
		},

		onCitySearch( event: Event ) {
			const context = getContext< ControlContext >();
			const input = event.target as HTMLInputElement;
			context.typed = input.value;
			context.query = input.value;
			context.open = true;
			context.active = -1;
			// Typing again drops the previous choice until a city is picked.
			context.code = '';
		},

		pickCity( event: MouseEvent ) {
			const item = ( event.target as HTMLElement ).closest< HTMLElement >( 'li[data-value]' );
			if ( ! item ) {
				return;
			}
			const context = getContext< ControlContext >();
			context.code = item.dataset.value || '';
			context.query = item.dataset.label || '';
			context.typed = context.query;
			context.open = false;
			context.active = -1;
		},

		onCityKeydown( event: KeyboardEvent ) {
			const context = getContext< ControlContext >();
			const list = matches( context, event.target as HTMLElement );
			// The field shows the highlighted city; what was typed still filters.
			const highlight = ( index: number ) => {
				context.active = index;
				context.query = list[ index ].label;
			};
			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					context.open = true;
					if ( list.length ) {
						highlight( ( ( context.active ?? -1 ) + 1 ) % list.length );
					}
					break;
				case 'ArrowUp':
					event.preventDefault();
					if ( list.length ) {
						highlight( ( context.active ?? 0 ) <= 0 ? list.length - 1 : ( context.active ?? 0 ) - 1 );
					}
					break;
				case 'Enter':
				case ' ': {
					// Enter or Space on a highlighted city chooses it and closes the
					// list; Enter with none highlighted takes the city typed in full.
					const chosen =
						list[ context.active ?? -1 ] ??
						( event.key === 'Enter'
							? exactCity( context, event.target as HTMLElement )
							: undefined );
					if ( context.open && chosen ) {
						event.preventDefault();
						context.code = chosen.value;
						context.query = chosen.label;
						context.typed = chosen.label;
						context.open = false;
						context.active = -1;
					}
					break;
				}
				case 'Escape':
					context.open = false;
					context.active = -1;
					context.query = context.typed ?? context.query;
					break;
			}
		},

		keepFocus( event: MouseEvent ) {
			event.preventDefault();
		},

		onCityFocusOut( event: FocusEvent ) {
			const wrapper = event.currentTarget as HTMLElement;
			const next = event.relatedTarget as Node | null;
			if ( next && wrapper.contains( next ) ) {
				return;
			}
			const context = getContext< ControlContext >();
			// Leaving without picking a city takes the city typed in full
			// ("joinville" → Joinville), or clears the text.
			if ( ! context.code ) {
				const typed = exactCity( context, wrapper );
				context.code = typed?.value ?? '';
				context.query = typed?.label ?? '';
				context.typed = context.query;
			}
			context.open = false;
			context.active = -1;
		},

		/** Phone mask by country: (11) 90000-0000, (555) 555-5555, or as typed. */
		onPhoneInput( event: Event ) {
			const context = getContext< ControlContext >();
			const country = countryOf( context );
			const input = event.target as HTMLInputElement;
			const digits = input.value.replace( /\D/g, '' );
			input.setCustomValidity( '' );
			if ( country === 'BR' ) {
				// Brazil tells the line apart: a mobile has 11 digits with 9 after
				// the area code, a landline 10 digits starting with 2 to 5.
				// With both, the first digit after the area code picks the line as
				// it is typed: 9 is a mobile, 2 to 5 a landline (else by length).
				const first = digits.charAt( 2 );
				const line = context.lineType ||
					( first === '9' ? 'mobile' : /[2-5]/.test( first ) ? 'landline' : '' );
				const v = digits.slice( 0, line === 'landline' ? 10 : 11 );
				const mobile = line === 'mobile' || ( ! line && v.length > 10 );
				input.value = mobile
					? ( v.length > 7 ? v.replace( /^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3' )
						: v.length > 2 ? v.replace( /^(\d{2})(\d{0,5}).*/, '($1) $2' )
						: v.replace( /^(\d*)/, v ? '($1' : '' ) )
					: ( v.length > 6 ? v.replace( /^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3' )
						: v.length > 2 ? v.replace( /^(\d{2})(\d{0,5}).*/, '($1) $2' )
						: v.replace( /^(\d*)/, v ? '($1' : '' ) );
				if ( v ) {
					const isMobile = /^\d{2}9\d{8}$/.test( v );
					const isLandline = /^\d{2}[2-5]\d{7}$/.test( v );
					const only = context.lineType;
					if ( only === 'mobile' && ! isMobile ) {
						input.setCustomValidity( 'Informe um celular com DDD: (11) 9XXXX-XXXX.' );
					} else if ( only === 'landline' && ! isLandline ) {
						input.setCustomValidity( 'Informe um telefone fixo com DDD: (11) XXXX-XXXX.' );
					} else if ( ! only && ! isMobile && ! isLandline ) {
						input.setCustomValidity( 'Informe um telefone com DDD.' );
					}
				}
			} else if ( country === 'US' ) {
				const v = digits.slice( 0, 10 );
				input.value =
					v.length > 6 ? v.replace( /^(\d{3})(\d{3})(\d{0,4}).*/, '($1) $2-$3' )
					: v.length > 3 ? v.replace( /^(\d{3})(\d{0,3}).*/, '($1) $2' )
					: v.replace( /^(\d*)/, v ? '($1' : '' );
			}
		},

		onPostalInput( event: Event ) {
			const context = getContext< ControlContext >();
			const input = event.target as HTMLInputElement;
			const country = countryOf( context );
			const digits = input.value.replace( /\D/g, '' );
			if ( country === 'BR' ) {
				const cep = digits.slice( 0, 8 );
				input.value = cep.length > 5 ? `${ cep.slice( 0, 5 ) }-${ cep.slice( 5 ) }` : cep;
			} else if ( country === 'US' ) {
				const zip = digits.slice( 0, 9 );
				input.value = zip.length > 5 ? `${ zip.slice( 0, 5 ) }-${ zip.slice( 5 ) }` : zip;
			}
		},
	},
	callbacks: {
		/** Remember the form of this control (getters cannot read the DOM yet). */
		initRegion() {
			const context = getContext< ControlContext >();
			const region = here();
			const form = region?.closest( 'form' );
			context.form = form?.getAttribute( 'data-form-id' ) || '';
			if ( ! form || ! region ) {
				return;
			}
			// After the form is reset (a successful submission): the city search
			// starts empty and the linked values follow the fields again (the
			// reset event comes before the fields get their defaults back).
			const onReset = withScope( () => {
				const ctx = getContext< ControlContext >();
				ctx.code = '';
				ctx.query = '';
				ctx.typed = '';
				ctx.open = false;
				ctx.active = -1;
				setTimeout( () => {
					const s = state as unknown as AddressState;
					region
						.querySelectorAll< HTMLInputElement | HTMLSelectElement >(
							'input[name], select[name]'
						)
						.forEach( ( field ) => {
							s.values[ keyOf( field, field.name ) ] = field.value;
						} );
				}, 0 );
			} );
			form.addEventListener( 'reset', onReset );
			return () => form.removeEventListener( 'reset', onReset );
		},

		/** The country already set on load (a hidden field or a preselected option). */
		initCountry() {
			const field = here() as HTMLInputElement | HTMLSelectElement;
			( state as unknown as AddressState ).values[ keyOf( field, field.name ) ] = field.value;
		},

		/** UF: the id goes to the active field only (one id in the form). */
		syncStateIds() {
			const context = getContext< ControlContext >();
			const list = ( state as unknown as { hasStateList: boolean } ).hasStateList;
			context.selectId = list ? context.fieldId || null : null;
			context.textId = list ? null : context.fieldId || null;
		},

		renderStates() {
			const s = state as unknown as AddressState & { country: string };
			const select = here() as HTMLSelectElement;
			const country = s.country;
			const list = STATES_BY_COUNTRY[ country ] || [];
			// A state of the previous country never carries over (SC is also South Carolina).
			const current = select.dataset.country === country ? select.value : '';
			select.dataset.country = country;
			// The empty option keeps the text it was rendered with (the placeholder).
			if ( select.dataset.empty === undefined ) {
				select.dataset.empty = select.options[ 0 ]?.value === '' ? select.options[ 0 ].text : '—';
			}
			select.replaceChildren( new Option( select.dataset.empty, '' ), ...list.map( ( uf ) => new Option( uf, uf ) ) );
			select.value = list.includes( current ) ? current : '';
			s.values[ keyOf( select, select.name ) ] = select.value;
		},

		renderCities() {
			const s = state as unknown as AddressState;
			const context = getContext< ControlContext >();
			const select = here() as HTMLSelectElement;
			const cities = s.cities[ keyOf( select, context.stateField || 'state' ) ] || [];
			const current = select.value;
			// The value is the city name.
			select.replaceChildren( new Option( '—', '' ), ...cities.map( ( city ) => new Option( city.label, city.value ) ) );
			select.value = current;
		},

		renderCitySearch() {
			const context = getContext< ControlContext >();
			const list = here() as HTMLElement;
			const items = matches( context, list ).map( ( city, index ) => {
				const li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.dataset.value = city.value;
				li.dataset.label = city.label;
				li.setAttribute( 'aria-selected', String( index === context.active ) );
				li.textContent = city.label;
				return li;
			} );
			list.replaceChildren( ...items );
		},
	},
} );

/**
 * The city of the linked state whose name, as a slug, is the typed text's.
 *
 * @param context Control context.
 * @param el      An element of the control (to find its form).
 */
function exactCity( context: ControlContext, el: Element ): Option | undefined {
	const s = state as unknown as AddressState;
	const cities = s.cities[ keyOf( el, context.stateField || 'state' ) ] || [];
	const q = slug( context.typed ?? context.query ?? '' );
	return q ? cities.find( ( c ) => slug( c.label ) === q ) : undefined;
}

/**
 * Cities of the linked state that match the typed text (start of name first).
 *
 * @param context Control context.
 * @param el      An element of the control (to find its form).
 */
function matches( context: ControlContext, el: Element ): Option[] {
	const s = state as unknown as AddressState;
	const cities = s.cities[ keyOf( el, context.stateField || 'state' ) ] || [];
	const q = fold( ( context.typed ?? context.query ?? '' ).trim() );
	if ( ! q ) {
		return [];
	}
	const starts = cities.filter( ( c ) => fold( c.label ).startsWith( q ) );
	const inner = cities.filter( ( c ) => ! fold( c.label ).startsWith( q ) && fold( c.label ).includes( q ) );
	return [ ...starts, ...inner ].slice( 0, MAX_CITIES );
}
