import { store, getContext, getElement } from '@wordpress/interactivity';
import { STATES_BY_COUNTRY } from './states';

interface Option {
	label: string;
	value: string;
}

interface AddressContext {
	/** Field values by name (pais, uf, cidade…), shared by the whole region. */
	values: Record< string, string >;
	/** Cities loaded for each state field, by its name. */
	cities: Record< string, Option[] >;
	/** Links of the current control (child context). */
	countryField?: string;
	stateField?: string;
	/** City search (child context): typed text, list open, active option. */
	query?: string;
	/** IBGE code of the city picked in the search (its hidden input). */
	code?: string;
	open?: boolean;
	active?: number;
}

const MAX_CITIES = 20;

/** Lower case, no accents, for matching typed city names. */
const fold = ( text: string ) =>
	text.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ).toLowerCase();

/** Cities of the linked state that match the typed text (start of word first). */
function matches( context: AddressContext ): Option[] {
	const cities = context.cities[ context.stateField || 'uf' ] || [];
	const q = fold( ( context.query || '' ).trim() );
	if ( ! q ) {
		return [];
	}
	const starts = cities.filter( ( c ) => fold( c.label ).startsWith( q ) );
	const inner = cities.filter( ( c ) => ! fold( c.label ).startsWith( q ) && fold( c.label ).includes( q ) );
	return [ ...starts, ...inner ].slice( 0, MAX_CITIES );
}

/** Field name of the city control in the current context (its hidden input). */
const cityName = ( element: HTMLElement ) =>
	element.closest( '.aac-city-search' )?.querySelector< HTMLInputElement >( 'input[type="hidden"]' )?.name || 'cidade';

const countryOf = ( context: AddressContext ) =>
	context.values[ context.countryField || 'pais' ] || '';

interface ServerState {
	citiesUrl: string;
	/** Countries whose cities come from the cities endpoint (filterable in PHP). */
	cityCountries: string[];
}

const server = () => state as unknown as ServerState;

/** The country has a city list (from the cities endpoint). */
const hasCities = ( country: string ) =>
	!! country && ( server().cityCountries || [] ).includes( country );

const { state } = store( 'axell/address', {
	state: {
		/** The state control shows a list (Brazil or United States). */
		get hasStateList(): boolean {
			return !! STATES_BY_COUNTRY[ countryOf( getContext< AddressContext >() ) ];
		},
		/** The city control shows a list (or search) when its country has cities. */
		get hasCityList(): boolean {
			return hasCities( countryOf( getContext< AddressContext >() ) );
		},
		get cityListOpen(): boolean {
			const context = getContext< AddressContext >();
			return !! context.open && ( context.query || '' ).trim() !== '';
		},
	},
	actions: {
		/** Keep the value of any linked field (country, free-text state or city). */
		onField( event: Event ) {
			const context = getContext< AddressContext >();
			const field = event.target as HTMLInputElement | HTMLSelectElement;
			context.values[ field.name ] = field.value;
		},

		*onState( event: Event ): Generator< unknown, void, unknown > {
			const context = getContext< AddressContext >();
			const select = event.target as HTMLSelectElement;
			const uf = select.value;
			context.values[ select.name ] = uf;
			context.cities[ select.name ] = [];
			const country = countryOf( context );
			if ( ! hasCities( country ) || ! uf ) {
				return;
			}

			// Cities of the state: the same REST route the form already uses.
			const url = new URL( server().citiesUrl );
			url.searchParams.set( 'uf', uf );
			url.searchParams.set( 'country', country );
			try {
				const response = ( yield fetch( url.toString() ) ) as Response;
				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}
				const cities = ( yield response.json() ) as Option[];
				if ( context.values[ select.name ] === uf ) {
					context.cities[ select.name ] = cities;
				}
			} catch {
				context.cities[ select.name ] = [];
			}
		},

		onCitySearch( event: Event ) {
			const context = getContext< AddressContext >();
			const input = event.target as HTMLInputElement;
			context.query = input.value;
			context.open = true;
			context.active = -1;
			// Typing again drops the previous choice until a city is picked.
			context.code = '';
			context.values[ cityName( input ) ] = '';
		},

		pickCity( event: MouseEvent ) {
			const item = ( event.target as HTMLElement ).closest< HTMLElement >( 'li[data-value]' );
			if ( ! item ) {
				return;
			}
			const context = getContext< AddressContext >();
			context.code = item.dataset.value || '';
			context.values[ cityName( item ) ] = context.code;
			context.query = item.dataset.label || '';
			context.open = false;
			context.active = -1;
		},

		onCityKeydown( event: KeyboardEvent ) {
			const context = getContext< AddressContext >();
			const list = matches( context );
			const input = event.target as HTMLInputElement;
			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					context.open = true;
					if ( list.length ) {
						context.active = ( ( context.active ?? -1 ) + 1 ) % list.length;
					}
					break;
				case 'ArrowUp':
					event.preventDefault();
					if ( list.length ) {
						context.active = ( context.active ?? 0 ) <= 0 ? list.length - 1 : ( context.active ?? 0 ) - 1;
					}
					break;
				case 'Enter': {
					const chosen = list[ context.active ?? -1 ];
					if ( context.open && chosen ) {
						event.preventDefault();
						context.code = chosen.value;
						context.values[ cityName( input ) ] = chosen.value;
						context.query = chosen.label;
						context.open = false;
						context.active = -1;
					}
					break;
				}
				case 'Escape':
					context.open = false;
					context.active = -1;
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
			const context = getContext< AddressContext >();
			// Leaving without picking a city clears the text.
			if ( ! context.code ) {
				context.query = '';
			}
			context.open = false;
			context.active = -1;
		},

		onPostalInput( event: Event ) {
			const country = countryOf( getContext< AddressContext >() );
			const input = event.target as HTMLInputElement;
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
		/** The country already set on load (a fixed hidden field or a preselected option). */
		initCountry() {
			const context = getContext< AddressContext >();
			const field = getElement().ref as HTMLInputElement | HTMLSelectElement;
			context.values[ field.name ] = field.value;
		},

		renderStates() {
			const context = getContext< AddressContext >();
			const select = getElement().ref as HTMLSelectElement;
			const country = countryOf( context );
			const list = STATES_BY_COUNTRY[ country ] || [];
			// A state of the previous country never carries over (SC is also South Carolina).
			const current = select.dataset.country === country ? select.value : '';
			select.dataset.country = country;
			select.replaceChildren(
				new Option( '—', '' ),
				...list.map( ( uf ) => new Option( uf, uf ) )
			);
			select.value = list.includes( current ) ? current : '';
			context.values[ select.name ] = select.value;
		},

		renderCitySearch() {
			const context = getContext< AddressContext >();
			const list = getElement().ref as HTMLElement;
			const items = matches( context ).map( ( city, index ) => {
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

		renderCities() {
			const context = getContext< AddressContext >();
			const select = getElement().ref as HTMLSelectElement;
			const cities = context.cities[ context.stateField || 'uf' ] || [];
			// The value is the IBGE code of the city, as the form has always sent it.
			select.replaceChildren(
				new Option( '—', '' ),
				...cities.map( ( city ) => new Option( city.label, city.value ) )
			);
			select.value = context.values[ select.name ] || '';
		},
	},
} );
