import { store, getContext, getElement } from '@wordpress/interactivity';

interface Option {
	id: number;
	label: string;
}

interface AutocompleteContext {
	postType: string;
	template: string;
	allowNotFound: boolean;
	text: string;
	selectedId: string;
	titulo: string;
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
function composeTitulo( context: AutocompleteContext ): string {
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
		context.text = option.label;
		context.titulo = option.label;
		context.notFound = false;
		context.custom = false;
	} else {
		context.selectedId = '';
		context.notFound = true;
		context.custom = true;
		context.customName = context.text.trim();
		context.titulo = composeTitulo( context );
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

			context.text = query;
			context.titulo = query;
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
			if ( context.text !== query ) {
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
				if ( context.text === query ) {
					context.options = options;
					context.loading = false;
				}
			} catch {
				if ( context.text === query ) {
					context.options = [];
					context.loading = false;
				}
			}
		},

		onCustomInput( event: Event ) {
			const input = event.target as HTMLInputElement;
			const context = getContext< AutocompleteContext >();
			context.customName = input.value;
			context.titulo = composeTitulo( context );
		},

		onCustomCity( event: Event ) {
			const select = event.target as HTMLSelectElement;
			const context = getContext< AutocompleteContext >();
			context.customCity = select.value;
			context.titulo = composeTitulo( context );
		},

		backToSearch( event: MouseEvent ) {
			const context = getContext< AutocompleteContext >();
			context.custom = false;
			context.notFound = false;
			context.selectedId = '';
			context.titulo = '';
			context.activeIndex = -1;
			// Back to the search with the list open (its options and the add row).
			context.open = context.text.trim() !== '';

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
			context.titulo = composeTitulo( context );

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
				( context.allowNotFound && context.text.trim() ? 1 : 0 );

			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					context.open = true;
					if ( total ) {
						context.activeIndex = ( context.activeIndex + 1 ) % total;
					}
					break;
				case 'ArrowUp':
					event.preventDefault();
					if ( total ) {
						context.activeIndex =
							context.activeIndex <= 0
								? total - 1
								: context.activeIndex - 1;
					}
					break;
				case 'Enter':
					if ( context.open && context.activeIndex >= 0 ) {
						event.preventDefault();
						choose( context, context.activeIndex );
					}
					break;
				case 'Escape':
					context.open = false;
					context.activeIndex = -1;
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
				context.text = '';
				context.titulo = '';
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
			const showNotFound = context.allowNotFound && context.text.trim() !== '';

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
				typed.textContent = context.text.trim();

				const add = document.createElement( 'button' );
				add.type = 'button';
				add.className = 'aa-ac-add';
				add.textContent = NOT_FOUND_LABEL;

				li.append( typed, add );
				items.push( li );
			}

			list.replaceChildren( ...items );
		},
	},
} );

export { state };
