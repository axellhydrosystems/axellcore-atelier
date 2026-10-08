/**
 * Member fields on the user edit screens (includes/class-member-profile.php):
 * the masks of the phone, CEP and CPF/CNPJ (stored without them), the
 * registration type that follows the CPF/CNPJ, and the city search over the
 * cities of the chosen state, as on the form.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';
import {
	guessType,
	isValidCNPJ,
	isValidCPF,
	mask as documentMask,
} from '../../form/form-control-br-revenue-id/document';

const digitsOnly = ( value: string ) => value.replace( /\D/g, '' );

/**
 * A Brazilian phone as the form masks it: the digit after the area code
 * tells the line as it is typed, 9 a mobile ((11) 98765-4321, 11 digits),
 * 2 to 5 a landline ((11) 3456-7890, 10 digits).
 *
 * @param value What was typed.
 */
const phoneMask = ( value: string ) => {
	const digits = digitsOnly( value );
	const first = digits.charAt( 2 );
	let line = '';
	if ( first === '9' ) {
		line = 'mobile';
	} else if ( /[2-5]/.test( first ) ) {
		line = 'landline';
	}
	const v = digits.slice( 0, line === 'landline' ? 10 : 11 );
	if ( v.length <= 2 ) {
		return v ? `(${ v }` : '';
	}
	const split = line === 'landline' || ( ! line && v.length <= 10 ) ? 4 : 5;
	const area = `(${ v.slice( 0, 2 ) }) `;
	const rest = v.slice( 2 );
	return rest.length > split
		? `${ area }${ rest.slice( 0, split ) }-${ rest.slice( split ) }`
		: area + rest;
};

/**
 * A CEP as 01001-000.
 *
 * @param value What was typed.
 */
const postalMask = ( value: string ) => {
	const digits = digitsOnly( value ).slice( 0, 8 );
	return digits.length > 5
		? `${ digits.slice( 0, 5 ) }-${ digits.slice( 5 ) }`
		: digits;
};

/**
 * The CPF/CNPJ masked only once its type is known: a letter (alphanumeric
 * CNPJ) or 11 digits and more; before that, the characters alone.
 *
 * @param value What was typed.
 */
const maskDocument = ( value: string ) => {
	const raw = value
		.toUpperCase()
		.replace( /[^0-9A-Z]/g, '' )
		.slice( 0, 14 );
	if ( /[A-Z]/.test( raw ) ) {
		return documentMask( raw, 'cnpj' );
	}
	return raw.length >= 11 ? documentMask( raw, guessType( raw ) ) : raw;
};

/**
 * individual for a valid CPF, legal_entity for a valid CNPJ, '' otherwise.
 *
 * @param value CPF/CNPJ.
 */
const profileType = ( value: string ) => {
	if ( isValidCPF( value ) ) {
		return 'individual';
	}
	return isValidCNPJ( value ) ? 'legal_entity' : '';
};

store( 'axell/member-fields', {
	actions: {
		mask( event: Event ) {
			const input = event.target as HTMLInputElement;
			switch ( input.dataset.mask ) {
				case 'phone':
					input.value = phoneMask( input.value );
					break;
				case 'postal':
					input.value = postalMask( input.value );
					break;
				case 'document': {
					input.value = maskDocument( input.value );
					// The registration type follows the number.
					const type = document.getElementById(
						'aa_member_profile_type'
					) as HTMLSelectElement | null;
					if ( type ) {
						type.value = profileType( input.value );
					}
					break;
				}
			}
		},
	},
} );

interface CityContext {
	value: string;
	options: string[];
	open: boolean;
	active: number;
	option?: string;
}

const MAX_CITIES = 20;
const DEBOUNCE_MS = 150;

/**
 * Lower case, without accents, for matching.
 * @param value Text.
 */
const fold = ( value: string ) =>
	value.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase().trim();

const cityState = ( id: string ) =>
	( document.getElementById( id ) as HTMLSelectElement | null )?.value ?? '';

/** The cities of a state, fetched once per state. */
const cache: Record< string, Promise< string[] > > = {};
const citiesOf = ( uf: string, url: string ): Promise< string[] > => {
	if ( ! cache[ uf ] ) {
		const request = new URL( url );
		request.searchParams.set( 'uf', uf );
		request.searchParams.set( 'country', 'BR' );
		cache[ uf ] = fetch( request.toString() )
			.then( ( response ) => ( response.ok ? response.json() : [] ) )
			.then( ( cities: Array< { label: string } > ) =>
				cities.map( ( city ) => city.label )
			)
			.catch( () => {
				delete cache[ uf ];
				return [];
			} );
	}
	return cache[ uf ];
};

/**
 * Cities starting with what was typed, then the ones containing it.
 *
 * @param cities The state's cities.
 * @param typed  What was typed.
 */
const matches = ( cities: string[], typed: string ) => {
	const query = fold( typed );
	if ( ! query ) {
		return [];
	}
	const starts = cities.filter( ( city ) =>
		fold( city ).startsWith( query )
	);
	const contains = cities.filter(
		( city ) =>
			! fold( city ).startsWith( query ) && fold( city ).includes( query )
	);
	return [ ...starts, ...contains ].slice( 0, MAX_CITIES );
};

const cityInput = () => {
	const { ref } = getElement();
	return ref
		?.closest( '.aa-member-city' )
		?.querySelector< HTMLInputElement >( 'input[role="combobox"]' );
};

const choose = (
	context: CityContext,
	input: HTMLInputElement,
	city: string
) => {
	input.value = city;
	context.value = city;
	context.open = false;
	context.active = -1;
};

const { state } = store( 'axell/member-city', {
	state: {
		get isOpen(): boolean {
			const context = getContext< CityContext >();
			return context.open && context.options.length > 0;
		},
		get activeId(): string {
			const context = getContext< CityContext >();
			return context.open && context.active >= 0
				? `aa_member_city-option-${ context.active }`
				: '';
		},
		get optionId(): string {
			const context = getContext< CityContext >();
			return `aa_member_city-option-${ context.options.indexOf(
				context.option ?? ''
			) }`;
		},
		get isActive(): boolean {
			const context = getContext< CityContext >();
			return context.options[ context.active ] === context.option;
		},
	},
	actions: {
		*onInput( event: Event ): Generator< unknown, void, unknown > {
			const context = getContext< CityContext >();
			const typed = ( event.target as HTMLInputElement ).value;
			const uf = cityState( 'aa_member_state' );
			context.value = typed;
			context.active = -1;
			if ( ! uf || ! typed.trim() ) {
				context.options = [];
				context.open = false;
				return;
			}
			yield new Promise( ( resolve ) =>
				setTimeout( resolve, DEBOUNCE_MS )
			);
			const server = state as unknown as { citiesUrl: string };
			const cities = ( yield citiesOf(
				uf,
				server.citiesUrl
			) ) as string[];
			// A newer keystroke wins.
			if ( ( event.target as HTMLInputElement ).value !== typed ) {
				return;
			}
			context.options = matches( cities, typed );
			context.open = true;
		},

		onKeydown( event: KeyboardEvent ) {
			const context = getContext< CityContext >();
			const total = context.options.length;
			const input = event.target as HTMLInputElement;
			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					if ( total ) {
						context.open = true;
						context.active = ( context.active + 1 ) % total;
					}
					break;
				case 'ArrowUp':
					event.preventDefault();
					if ( total ) {
						context.open = true;
						context.active =
							context.active <= 0
								? total - 1
								: context.active - 1;
					}
					break;
				case 'Enter':
					// Never submit the profile from the search field.
					event.preventDefault();
					if ( context.open && context.options[ context.active ] ) {
						choose(
							context,
							input,
							context.options[ context.active ]
						);
					}
					break;
				case 'Escape':
					if ( context.open ) {
						event.preventDefault();
						context.open = false;
						context.active = -1;
					}
					break;
			}
		},

		pick( event: MouseEvent ) {
			// Mousedown, before the field loses the focus and closes the list.
			event.preventDefault();
			const context = getContext< CityContext >();
			const input = cityInput();
			if ( input && context.option ) {
				choose( context, input, context.option );
			}
		},

		*onFocusOut( event: FocusEvent ): Generator< unknown, void, unknown > {
			const wrapper = event.currentTarget as HTMLElement;
			const next = event.relatedTarget as Node | null;
			if ( next && wrapper.contains( next ) ) {
				return;
			}
			const context = getContext< CityContext >();
			context.open = false;
			context.active = -1;

			// The city typed in full takes its proper name ("joinville" →
			// Joinville); anything else is cleared, as on the form.
			const input = wrapper.querySelector< HTMLInputElement >(
				'input[role="combobox"]'
			);
			const uf = cityState( 'aa_member_state' );
			if ( ! input || ! input.value.trim() || ! uf ) {
				return;
			}
			const server = state as unknown as { citiesUrl: string };
			const cities = ( yield citiesOf(
				uf,
				server.citiesUrl
			) ) as string[];
			const exact = cities.find(
				( city ) => fold( city ) === fold( input.value )
			);
			input.value = exact ?? '';
			context.value = input.value;
		},

		onState() {
			// Another state: the city chosen no longer belongs to it.
			const city = document.getElementById(
				'aa_member_city'
			) as HTMLInputElement | null;
			if ( city ) {
				city.value = '';
				// Through the city's own input action, which keeps its value.
				city.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
		},
	},
} );
