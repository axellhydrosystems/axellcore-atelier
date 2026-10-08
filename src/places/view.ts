/**
 * Google's address suggestions on a street field (includes/class-places.php):
 * typing asks this site's /places/autocomplete, and choosing an address asks
 * /places/details and fills in the other address fields of the form, through
 * the events those fields already follow (the state's change loads its
 * cities, the city search takes the city typed in full, the CEP is masked).
 */
import {
	store,
	getContext,
	getElement,
	withScope,
} from '@wordpress/interactivity';

interface Suggestion {
	id: string;
	main: string;
	secondary: string;
}

/** The fields an address fills in, by role: their names in the form. */
interface Fields {
	street: string;
	number: string;
	complement: string;
	neighborhood: string;
	state: string;
	city: string;
	postal: string;
}

interface Address {
	address_street: string;
	address_number: string;
	/** An apartment or room Google knows ("ap 103"). */
	address_2: string;
	neighborhood: string;
	city: string;
	state: string;
	postal: string;
}

interface PlacesContext {
	fields: Fields;
	items: Suggestion[];
	open: boolean;
	active: number;
	/** What was typed (a newer input drops an older answer). */
	typed: string;
	/** Session token: from the first search to the address chosen. */
	session?: string;
	/** Waiting for Google's suggestions. */
	loading: boolean;
}

interface PlacesState {
	autocompleteUrl: string;
	detailsUrl: string;
	minInput: number;
	/** "Searching addresses…", translated. */
	searching: string;
}

/** Time without typing before asking for suggestions. */
const DEBOUNCE_MS = 200;

/** How long the city waits for the state's cities. */
const CITIES_WAIT_MS = 5000;

const sleep = ( ms: number ) =>
	new Promise< void >( ( resolve ) => setTimeout( resolve, ms ) );

const sessionToken = (): string =>
	typeof crypto !== 'undefined' && 'randomUUID' in crypto
		? crypto.randomUUID()
		: Math.random().toString( 36 ).slice( 2 ) + Date.now().toString( 36 );

const server = (): PlacesState => state as unknown as PlacesState;

/** The editable field of a name in the form: not hidden, not disabled. */
const fieldOf = (
	form: HTMLFormElement,
	name: string
): HTMLInputElement | HTMLSelectElement | undefined =>
	Array.from(
		form.querySelectorAll< HTMLInputElement | HTMLSelectElement >(
			`[name="${ CSS.escape( name ) }"]`
		)
	).find(
		( el ) =>
			( el as HTMLInputElement ).type !== 'hidden' &&
			! el.hidden &&
			! el.disabled
	);

/** Set a field as if typed: its input (or change) handlers follow. */
const setField = (
	el: HTMLInputElement | HTMLSelectElement | undefined,
	value: string,
	type: 'input' | 'change' = 'input'
) => {
	if ( ! el ) {
		return;
	}
	el.value = value;
	el.dispatchEvent( new Event( type, { bubbles: true } ) );
};

/**
 * The city: once the state's cities are loaded, typed in the city search and
 * taken in full as the field is left (or set in a free-text field).
 *
 * @param form Form.
 * @param name City field name.
 * @param city City name.
 */
const setCity = async ( form: HTMLFormElement, name: string, city: string ) => {
	// The search has no name: the city goes in a hidden field beside it.
	const search = form
		.querySelector( `input[type="hidden"][name="${ CSS.escape( name ) }"]` )
		?.closest( '.aa-city-search' )
		?.querySelector< HTMLInputElement >(
			'[role="combobox"]:not([disabled])'
		);
	if ( ! search ) {
		setField( fieldOf( form, name ), city );
		return;
	}
	const address = store( 'axell/address' ).state as unknown as {
		cities: Record< string, unknown[] >;
	};
	const key = `${ form.getAttribute( 'data-form-id' ) || '' }|state`;
	for (
		let waited = 0;
		waited < CITIES_WAIT_MS && ! address.cities?.[ key ]?.length;
		waited += 100
	) {
		await sleep( 100 );
	}
	setField( search, city );
	search.dispatchEvent( new FocusEvent( 'focusout', { bubbles: true } ) );
};

/**
 * Fill in the form with an address. The cursor goes to the first of number,
 * complement, neighborhood and CEP still empty (the complement when all are
 * filled), for the visitor to check or complete.
 *
 * @param form    Form.
 * @param fields  Field names.
 * @param address Address.
 */
const fill = async (
	form: HTMLFormElement,
	fields: Fields,
	address: Address
) => {
	const street = fieldOf( form, fields.street );
	if ( street ) {
		// No input event: it would search again.
		street.value = address.address_street;
	}
	setField( fieldOf( form, fields.number ), address.address_number );
	if ( address.address_2 ) {
		setField( fieldOf( form, fields.complement ), address.address_2 );
	}
	if ( address.neighborhood ) {
		setField( fieldOf( form, fields.neighborhood ), address.neighborhood );
	}
	if ( address.postal ) {
		setField( fieldOf( form, fields.postal ), address.postal );
	}
	const next =
		[ fields.number, fields.complement, fields.neighborhood, fields.postal ]
			.map( ( name ) => fieldOf( form, name ) )
			.find( ( el ) => el && ! el.value ) ??
		fieldOf( form, fields.complement );
	next?.focus();
	if ( address.state ) {
		setField( fieldOf( form, fields.state ), address.state, 'change' );
		if ( address.city ) {
			await setCity( form, fields.city, address.city );
			next?.focus();
		}
	}
};

const { state } = store( 'axell/places', {
	state: {
		get isOpen(): boolean {
			const context = getContext< PlacesContext >();
			return (
				context.open && ! context.loading && context.items.length > 0
			);
		},
		get isSearching(): boolean {
			return getContext< PlacesContext >().loading;
		},
	},
	actions: {
		*search( event: Event ): Generator< unknown, void, unknown > {
			const context = getContext< PlacesContext >();
			const input = event.target as HTMLInputElement;
			const typed = input.value;
			context.typed = typed;
			context.active = -1;
			if ( typed.trim().length < server().minInput ) {
				context.items = [];
				context.open = false;
				context.loading = false;
				return;
			}
			yield sleep( DEBOUNCE_MS );
			if ( context.typed !== typed ) {
				return;
			}
			context.loading = true;
			context.session = context.session || sessionToken();
			const url = new URL( server().autocompleteUrl );
			url.searchParams.set( 'input', typed.trim() );
			url.searchParams.set( 'session', context.session );
			try {
				const response = ( yield fetch( url.toString() ) ) as Response;
				const items = response.ok
					? ( ( yield response.json() ) as Suggestion[] )
					: [];
				if ( context.typed === typed ) {
					context.items = Array.isArray( items ) ? items : [];
					context.open = true;
					context.loading = false;
				}
			} catch {
				context.items = [];
				context.loading = false;
			}
		},

		keydown( event: KeyboardEvent ) {
			const context = getContext< PlacesContext >();
			const count = context.items.length;
			if ( ! context.open || ! count ) {
				return;
			}
			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					context.active = ( context.active + 1 ) % count;
					break;
				case 'ArrowUp':
					event.preventDefault();
					context.active =
						context.active <= 0 ? count - 1 : context.active - 1;
					break;
				case 'Enter':
					if ( context.active >= 0 ) {
						event.preventDefault();
						choose( event.target as HTMLElement, context.active );
					}
					break;
				case 'Escape':
					context.open = false;
					context.active = -1;
					break;
			}
		},

		pick( event: MouseEvent ) {
			const item = ( event.target as HTMLElement ).closest< HTMLElement >(
				'li[data-index]'
			);
			if ( item ) {
				choose( item, Number( item.dataset.index ) );
			}
		},

		keepFocus( event: MouseEvent ) {
			event.preventDefault();
		},

		close( event: FocusEvent ) {
			const wrapper = event.currentTarget as HTMLElement;
			const next = event.relatedTarget as Node | null;
			if ( next && wrapper.contains( next ) ) {
				return;
			}
			const context = getContext< PlacesContext >();
			context.open = false;
			context.active = -1;
			context.loading = false;
		},
	},
	callbacks: {
		render() {
			const context = getContext< PlacesContext >();
			const list = getElement().ref as HTMLElement;
			const items = context.items.map( ( item, index ) => {
				const li = document.createElement( 'li' );
				li.id = `${ list.id }-${ index }`;
				li.setAttribute( 'role', 'option' );
				li.dataset.index = String( index );
				li.setAttribute(
					'aria-selected',
					String( index === context.active )
				);
				const main = document.createElement( 'span' );
				main.className = 'aa-places-main';
				main.textContent = item.main;
				const secondary = document.createElement( 'span' );
				secondary.className = 'aa-places-secondary';
				secondary.textContent = item.secondary;
				li.append( main, secondary );
				return li;
			} );
			list.replaceChildren( ...items );
			// The field names the highlighted option for screen readers.
			const input = list
				.closest( '.aa-places-search' )
				?.querySelector< HTMLInputElement >( '[role="combobox"]' );
			if ( input ) {
				if ( context.active >= 0 ) {
					input.setAttribute(
						'aria-activedescendant',
						`${ list.id }-${ context.active }`
					);
				} else {
					input.removeAttribute( 'aria-activedescendant' );
				}
			}
		},
	},
} );

/**
 * Choose a suggestion: its address from /places/details fills in the form.
 *
 * @param el    An element of the search (to find its form).
 * @param index Suggestion.
 */
function choose( el: HTMLElement, index: number ) {
	const context = getContext< PlacesContext >();
	const item = context.items[ index ];
	const form = el.closest( 'form' );
	if ( ! item || ! form ) {
		return;
	}
	const session = context.session || '';
	// The details end the session: the next search starts another.
	context.session = '';
	context.open = false;
	context.active = -1;
	context.items = [];
	const fields = { ...context.fields };
	const url = new URL( server().detailsUrl );
	url.searchParams.set( 'id', item.id );
	url.searchParams.set( 'session', session );
	fetch( url.toString() )
		.then( ( response ) => ( response.ok ? response.json() : null ) )
		.then(
			withScope( ( address: Address | null ) => {
				if ( address ) {
					fill( form, fields, address );
				}
			} )
		)
		.catch( () => undefined );
}

export { state };
