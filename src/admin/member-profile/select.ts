/**
 * A select with a search, as WooCommerce's state field (selectWoo): a button
 * shows the choice and opens the options with a filter over them. The native
 * select stays in the form, hidden, and is what is sent (without JavaScript
 * it is the field). See Member_Profile::print_searchable_select().
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

interface Item {
	value: string;
	label: string;
}

interface SelectContext {
	value: string;
	label: string;
	items: Item[];
	query: string;
	open: boolean;
	active: number;
	ready: boolean;
	listId: string;
	item?: Item;
}

/**
 * Lower case, without accents, for matching.
 *
 * @param value Text.
 */
const fold = ( value: string ) =>
	value
		.normalize( 'NFD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.toLowerCase();

/**
 * The options that match the filter: by label or value (the UF).
 *
 * @param context Select context.
 */
const filtered = ( context: SelectContext ) => {
	const query = fold( context.query.trim() );
	if ( ! query ) {
		return context.items;
	}
	return context.items.filter(
		( item ) =>
			fold( item.label ).includes( query ) || fold( item.value ) === query
	);
};

const wrapper = () =>
	getElement().ref?.closest< HTMLElement >( '.aa-member-select' );

const close = ( context: SelectContext, focusToggle: boolean ) => {
	context.open = false;
	context.active = -1;
	context.query = '';
	if ( focusToggle ) {
		wrapper()
			?.querySelector< HTMLButtonElement >( '.aa-member-select__toggle' )
			?.focus();
	}
};

const choose = ( context: SelectContext, item: Item ) => {
	context.value = item.value;
	context.label = item.label;
	// The select is what the form sends; its change runs its own actions.
	const select = wrapper()?.querySelector( 'select' );
	if ( select ) {
		select.value = item.value;
		select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
	close( context, true );
};

store( 'axell/member-select', {
	state: {
		get shown(): string {
			return getContext< SelectContext >().label || ' ';
		},
		get filtered(): Item[] {
			return filtered( getContext< SelectContext >() );
		},
		get hasResults(): boolean {
			return filtered( getContext< SelectContext >() ).length > 0;
		},
		get activeId(): string {
			const context = getContext< SelectContext >();
			return context.active >= 0
				? `${ context.listId }-${ context.active }`
				: '';
		},
		get itemId(): string {
			const context = getContext< SelectContext >();
			const index = filtered( context ).findIndex(
				( item ) => item.value === context.item?.value
			);
			return `${ context.listId }-${ index }`;
		},
		get isActive(): boolean {
			const context = getContext< SelectContext >();
			return (
				filtered( context )[ context.active ]?.value ===
				context.item?.value
			);
		},
		get isCurrent(): boolean {
			const context = getContext< SelectContext >();
			return context.item?.value === context.value;
		},
	},
	actions: {
		toggle( event: MouseEvent ) {
			// A disabled field (as the native one) never opens.
			if ( ( event.currentTarget as HTMLButtonElement ).disabled ) {
				return;
			}
			const context = getContext< SelectContext >();
			if ( context.open ) {
				close( context, false );
				return;
			}
			context.open = true;
			context.query = '';
			// Start on the current choice.
			context.active = context.items.findIndex(
				( item ) => item.value === context.value
			);
			// The element now: getElement() has no scope in a timeout.
			const box = wrapper();
			setTimeout( () => {
				box?.querySelector< HTMLInputElement >(
					'.aa-member-select__search'
				)?.focus();
				box?.querySelector( '[aria-selected="true"]' )?.scrollIntoView(
					{
						block: 'nearest',
					}
				);
			}, 0 );
		},

		onSearch( event: Event ) {
			const context = getContext< SelectContext >();
			context.query = ( event.target as HTMLInputElement ).value;
			context.active = filtered( context ).length ? 0 : -1;
		},

		onKeydown( event: KeyboardEvent ) {
			const context = getContext< SelectContext >();
			const target = event.target as HTMLElement;
			const onToggle = target.classList.contains(
				'aa-member-select__toggle'
			);
			// Typing on the button opens the list and starts the filter with
			// that key, as selectWoo does.
			if (
				onToggle &&
				event.key.length === 1 &&
				! event.ctrlKey &&
				! event.metaKey &&
				! event.altKey
			) {
				event.preventDefault();
				if ( ! context.open ) {
					( target as HTMLButtonElement ).click();
				}
				context.query += event.key;
				context.active = filtered( context ).length ? 0 : -1;
				return;
			}
			if ( onToggle && ! context.open ) {
				// Arrows open the list from the button, as a select does.
				if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
					event.preventDefault();
					( target as HTMLButtonElement ).click();
				}
				return;
			}
			if ( ! context.open ) {
				return;
			}
			const list = filtered( context );
			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					if ( list.length ) {
						context.active = ( context.active + 1 ) % list.length;
					}
					break;
				case 'ArrowUp':
					event.preventDefault();
					if ( list.length ) {
						context.active =
							context.active <= 0
								? list.length - 1
								: context.active - 1;
					}
					break;
				case 'Enter':
					// Never submit the profile from the filter.
					event.preventDefault();
					if ( list[ context.active ] ) {
						choose( context, list[ context.active ] );
					}
					break;
				case 'Escape':
					event.preventDefault();
					close( context, true );
					break;
				case 'Tab':
					close( context, false );
					break;
			}
			// Keep the highlighted option in view.
			const box = wrapper();
			setTimeout( () => {
				box?.querySelector( '[aria-selected="true"]' )?.scrollIntoView(
					{
						block: 'nearest',
					}
				);
			}, 0 );
		},

		onNativeChange( event: Event ) {
			// Another script set the native select (the registration type
			// follows the CPF/CNPJ): the button shows it.
			const select = event.target as HTMLSelectElement;
			const context = getContext< SelectContext >();
			context.value = select.value;
			context.label =
				select.options[ select.selectedIndex ]?.textContent ?? '';
		},

		pick( event: MouseEvent ) {
			// Mousedown, before the filter loses the focus.
			event.preventDefault();
			const context = getContext< SelectContext >();
			if ( context.item ) {
				choose( context, context.item );
			}
		},

		onFocusOut( event: FocusEvent ) {
			const next = event.relatedTarget as Node | null;
			const box = event.currentTarget as HTMLElement;
			if ( ! next || ! box.contains( next ) ) {
				const context = getContext< SelectContext >();
				if ( context.open ) {
					close( context, false );
				}
			}
		},
	},
	callbacks: {
		init() {
			// With JavaScript, the button replaces the native select.
			getContext< SelectContext >().ready = true;
		},
	},
} );
