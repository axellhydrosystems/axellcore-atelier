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
	activeIndex: number;
	options: Option[];
}

const DEBOUNCE_MS = 250;
const NOT_FOUND_LABEL = 'Não encontrada';

/**
 * optionsUrl is provided by includes/class-form-block.php (wp_interactivity_state).
 */
const serverState = (): { optionsUrl: string } =>
	state as unknown as { optionsUrl: string };

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
	} else {
		context.selectedId = '';
		context.titulo = context.text;
		context.notFound = true;
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
			context.activeIndex = -1;
			context.open = query.trim() !== '';

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
				}
			} catch {
				if ( context.text === query ) {
					context.options = [];
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
			context.open = false;
			context.activeIndex = -1;
		},
	},

	callbacks: {
		// The list is rebuilt whenever the options, the active item or the text change.
		renderList() {
			const context = getContext< AutocompleteContext >();
			const list = getElement().ref as HTMLElement;

			const entries = context.options.map( ( option ) => option.label );
			if ( context.allowNotFound && context.text.trim() ) {
				entries.push( NOT_FOUND_LABEL );
			}

			const items = entries.map( ( label, index ) => {
				const li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.dataset.index = String( index );
				li.setAttribute( 'aria-selected', String( index === context.activeIndex ) );
				li.textContent = label;
				return li;
			} );

			list.replaceChildren( ...items );
		},
	},
} );

export { state };
