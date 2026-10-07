import { store, getContext, getElement } from '@wordpress/interactivity';

interface Option {
	id: number;
	label: string;
}

interface AutocompleteContext {
	postType: string;
	template: string;
	allowNotFound: boolean;
	/** What was typed: drives the search (text shows the highlighted item). */
	query: string;
	/** The field's value: the typed text, or the highlighted option's label. */
	text: string;
	selectedId: string;
	title: string;
	open: boolean;
	notFound: boolean;
	loading: boolean;
	custom: boolean;
	customName: string;
	customUf: string;
	customCity: string;
	cityOptions: CityOption[];
	activeIndex: number;
	options: Option[];
}

interface CityOption {
	label: string;
	value: string;
}

const DEBOUNCE_MS = 250;
const NOT_FOUND_LABEL = 'Adicionar não encontrada';

/**
 * optionsUrl is provided by includes/class-form-block.php (wp_interactivity_state).
 */
const serverState = (): { optionsUrl: string } =>
	state as unknown as { optionsUrl: string };

/**
 * The custom store text "Nome - UF Cidade", or '' while any part is missing.
 *
 * @param context Autocomplete context.
 */
function composeTitle( context: AutocompleteContext ): string {
	const nome = context.customName.trim();
	const cidade = context.customCity.trim();
	return nome && context.customUf && cidade
		? `${ nome } - ${ context.customUf } ${ cidade }`
		: '';
}

/**
 * Select the option at index; an index past the list is "Não encontrada".
 *
 * @param context Autocomplete context.
 * @param index   Position in the list.
 */
function choose( context: AutocompleteContext, index: number ) {
	if ( index < context.options.length ) {
		const option = context.options[ index ];
		context.selectedId = String( option.id );
		context.query = option.label;
		context.text = option.label;
		context.title = option.label;
		context.notFound = false;
		context.custom = false;
	} else {
		context.selectedId = '';
		context.notFound = true;
		context.custom = true;
		context.customName = context.query.trim();
		context.title = composeTitle( context );
	}
	context.open = false;
	context.activeIndex = -1;
}

const { state } = store( 'axell/autocomplete', {
	state: {},
	actions: {
		*onInput( event: Event ): Generator< unknown, void, unknown > {
			const input = event.target as HTMLInputElement;
			const context = getContext< AutocompleteContext >();
			const query = input.value;

			context.query = query;
			context.text = query;
			context.title = query;
			context.selectedId = '';
			context.notFound = false;
			context.custom = false;
			context.activeIndex = -1;
			context.open = query.trim() !== '';
			context.loading = context.open;

			if ( ! context.open ) {
				context.options = [];
				return;
			}

			// Debounce: a newer keystroke makes this request stale.
			yield new Promise( ( resolve ) => setTimeout( resolve, DEBOUNCE_MS ) );
			if ( context.query !== query ) {
				return;
			}

			const url = new URL( serverState().optionsUrl );
			url.searchParams.set( 'post_type', context.postType );
			url.searchParams.set( 'template', context.template );
			url.searchParams.set( 'q', query.trim() );

			try {
				const response = ( yield fetch( url.toString() ) ) as Response;
				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}
				const options = ( yield response.json() ) as Option[];
				if ( context.query === query ) {
					context.options = options;
					context.loading = false;
				}
			} catch {
				if ( context.query === query ) {
					context.options = [];
					context.loading = false;
				}
			}
		},

		onCustomInput( event: Event ) {
			const input = event.target as HTMLInputElement;
			const context = getContext< AutocompleteContext >();
			context.customName = input.value;
			context.title = composeTitle( context );
		},

		onCustomCity( event: Event ) {
			const select = event.target as HTMLSelectElement;
			const context = getContext< AutocompleteContext >();
			context.customCity = select.value;
			context.title = composeTitle( context );
		},

		backToSearch( event: MouseEvent ) {
			const context = getContext< AutocompleteContext >();
			context.custom = false;
			context.notFound = false;
			context.selectedId = '';
			context.title = '';
			context.activeIndex = -1;
			// Back to the search with the list open (its options and the add row).
			context.text = context.query;
			context.open = context.query.trim() !== '';

			// Focus the search input once it is shown again.
			const button = event.currentTarget as HTMLElement;
			const input = button
				.closest( '[data-wp-interactive="axell/autocomplete"]' )
				?.querySelector< HTMLInputElement >( 'input[role="combobox"]' );
			setTimeout( () => input?.focus(), 0 );
		},

		*onCustomUf( event: Event ): Generator< unknown, void, unknown > {
			const select = event.target as HTMLSelectElement;
			const context = getContext< AutocompleteContext >();
			context.customUf = select.value;
			context.customCity = '';
			context.cityOptions = [];
			context.title = composeTitle( context );

			if ( ! select.value ) {
				return;
			}

			// The cities of the chosen UF, from the same REST root as the options.
			const url = new URL( serverState().optionsUrl );
			url.pathname = url.pathname.replace( /options$/, 'cities' );
			url.search = `uf=${ encodeURIComponent( select.value ) }`;

			try {
				const response = ( yield fetch( url.toString() ) ) as Response;
				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}
				const cities = ( yield response.json() ) as CityOption[];
				if ( context.customUf === select.value ) {
					context.cityOptions = cities;
				}
			} catch {
				if ( context.customUf === select.value ) {
					context.cityOptions = [];
				}
			}
		},

		onKeydown( event: KeyboardEvent ) {
			const context = getContext< AutocompleteContext >();
			const total =
				context.options.length +
				( context.allowNotFound && context.query.trim() ? 1 : 0 );

			// The field shows the highlighted item: an option's label, or what
			// was typed for the "not found" row.
			const highlight = ( index: number ) => {
				context.activeIndex = index;
				context.text =
					index < context.options.length
						? context.options[ index ].label
						: context.query;
			};

			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					context.open = true;
					if ( total ) {
						highlight( ( context.activeIndex + 1 ) % total );
					}
					break;
				case 'ArrowUp':
					event.preventDefault();
					if ( total ) {
						highlight( context.activeIndex <= 0 ? total - 1 : context.activeIndex - 1 );
					}
					break;
				case 'Enter':
				case ' ':
					// Enter or Space on a highlighted item chooses it and closes the list.
					if ( context.open && context.activeIndex >= 0 ) {
						event.preventDefault();
						choose( context, context.activeIndex );
					}
					break;
				case 'Escape':
					context.open = false;
					context.activeIndex = -1;
					context.text = context.query;
					break;
			}
		},

		pick( event: MouseEvent ) {
			const item = ( event.target as HTMLElement ).closest< HTMLElement >(
				'li[data-index]'
			);
			if ( item ) {
				choose( getContext< AutocompleteContext >(), Number( item.dataset.index ) );
			}
		},

		// Keeps focus in the input while the list is clicked.
		keepFocus( event: MouseEvent ) {
			event.preventDefault();
		},

		onFocusOut( event: FocusEvent ) {
			const wrapper = event.currentTarget as HTMLElement;
			const next = event.relatedTarget as Node | null;
			if ( next && wrapper.contains( next ) ) {
				return;
			}
			const context = getContext< AutocompleteContext >();
			// Leaving without a valid choice (no item picked, no custom store) clears the text.
			if ( ! context.selectedId && ! context.custom ) {
				context.query = '';
				context.text = '';
				context.title = '';
				context.options = [];
				context.loading = false;
			}
			context.open = false;
			context.activeIndex = -1;
		},
	},

	callbacks: {
		// The city select lists the cities of the chosen UF.
		renderCities() {
			const context = getContext< AutocompleteContext >();
			const select = getElement().ref as HTMLSelectElement;
			const placeholder = new Option(
				context.customUf ? 'Cidade' : 'Selecione UF',
				''
			);
			// The city name is the value: that is what the title is built from.
			const cities = context.cityOptions.map(
				( city ) => new Option( city.label, city.label )
			);
			select.replaceChildren( placeholder, ...cities );
			select.value = context.customCity;
		},

		// The list is rebuilt whenever the options, the active item or the text change.
		renderList() {
			const context = getContext< AutocompleteContext >();
			const list = getElement().ref as HTMLElement;

			if ( context.loading ) {
				const searching = document.createElement( 'li' );
				searching.className = 'aa-ac-loading';
				searching.textContent = 'Procurando…';
				list.replaceChildren( searching );
				return;
			}

			const entries = context.options.map( ( option ) => option.label );
			const showNotFound = context.allowNotFound && context.query.trim() !== '';

			const items = entries.map( ( label, index ) => {
				const li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.dataset.index = String( index );
				li.setAttribute( 'aria-selected', String( index === context.activeIndex ) );
				li.textContent = label;
				return li;
			} );

			if ( showNotFound ) {
				// The typed text itself, with the add action at its end.
				const li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.className = 'aa-ac-notfound';
				li.dataset.index = String( entries.length );
				li.setAttribute( 'aria-selected', String( entries.length === context.activeIndex ) );

				const typed = document.createElement( 'span' );
				typed.textContent = context.query.trim();

				const add = document.createElement( 'button' );
				add.type = 'button';
				add.className = 'aa-ac-add';
				// Not a Tab stop: the row is chosen with the arrows and Enter/Space.
				add.tabIndex = -1;
				add.textContent = NOT_FOUND_LABEL;

				li.append( typed, add );
				items.push( li );
			}

			list.replaceChildren( ...items );
		},
	},
} );

export { state };
