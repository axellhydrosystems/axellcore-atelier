/**
 * Selects of the forms as the profile's (src/admin/member-profile/select.ts):
 * a button shows the choice and opens the options, with a filter or not.
 * The native select stays under the button and is what is sent and
 * validated; its options are read from it (the state's change with the
 * country) and choosing one sets it and fires its change, so its own actions
 * run as before. See includes/class-enhanced-select.php.
 */
import {
	store,
	getContext,
	getElement,
	withScope,
} from '@wordpress/interactivity';

interface Item {
	value: string;
	label: string;
}

interface SelectContext {
	search: boolean;
	/** "uf": a state, listed as "SP · São Paulo", shown as "SP". */
	kind: string;
	listId: string;
	items: Item[];
	value: string;
	/** The empty option's text ("Selecione"), shown when nothing is chosen. */
	placeholder: string;
	query: string;
	open: boolean;
	active: number;
	ready: boolean;
	/** The native select's own state (another store may set them). */
	hidden: boolean;
	disabled: boolean;
	item?: Item;
}

interface SelectState {
	/** State names by UF, in the site's language. */
	ufNames: Record< string, string >;
}

/** The native select's looks the button takes (it has no arrow of its own). */
const LOOKS = [
	'font-family',
	'font-size',
	'font-weight',
	'font-style',
	'line-height',
	'letter-spacing',
	'text-transform',
	'color',
	'background-color',
	'padding-top',
	'padding-right',
	'padding-bottom',
	'padding-left',
	'border-top',
	'border-right',
	'border-bottom',
	'border-left',
	'border-radius',
	'box-sizing',
	'height',
	'margin-top',
	'margin-right',
	'margin-bottom',
	'margin-left',
];

/**
 * Lower case, without accents, for matching.
 *
 * @param value Text.
 */
const fold = ( value: string ) =>
	value.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' ).toLowerCase();

const names = (): Record< string, string > =>
	( state as unknown as SelectState ).ufNames || {};

/**
 * An option's text in the list: the state's name for a UF, else its label.
 *
 * @param context Select context.
 * @param item    Option.
 */
const textOf = ( context: SelectContext, item: Item ) =>
	context.kind === 'uf' ? names()[ item.value ] || item.label : item.label;

/**
 * The options that match the filter: by text, or by the UF's start ("sc").
 *
 * @param context Select context.
 */
const filtered = ( context: SelectContext ): Item[] => {
	const query = fold( context.query.trim() );
	if ( ! query ) {
		return context.items;
	}
	return context.items.filter(
		( item ) =>
			fold( textOf( context, item ) ).includes( query ) ||
			( context.kind === 'uf' && fold( item.value ).startsWith( query ) )
	);
};

const nativeOf = ( box: Element | null | undefined ) =>
	box?.querySelector< HTMLSelectElement >( 'select' ) ?? null;

const toggleOf = ( box: Element | null | undefined ) =>
	box?.querySelector< HTMLButtonElement >( '.aa-select__toggle' ) ?? null;

const wrapper = () =>
	getElement().ref?.closest< HTMLElement >( '.aa-select' ) ?? null;

/** Keep the highlighted option in view (after the list renders). */
const scrollActive = ( box: HTMLElement | null ) => {
	setTimeout( () => {
		box?.querySelector( '[aria-selected="true"]' )?.scrollIntoView( {
			block: 'nearest',
		} );
	}, 0 );
};

const close = ( context: SelectContext, box: HTMLElement | null ) => {
	context.open = false;
	context.active = -1;
	context.query = '';
	toggleOf( box )?.focus();
};

const choose = (
	context: SelectContext,
	box: HTMLElement | null,
	item: Item
) => {
	context.value = item.value;
	const select = nativeOf( box );
	if ( select ) {
		select.value = item.value;
		select.setCustomValidity( '' );
		select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
	close( context, box );
};

/**
 * Read the native select into the context: its options, value and state.
 *
 * @param context Select context.
 * @param select  Native select.
 */
const read = ( context: SelectContext, select: HTMLSelectElement ) => {
	const options = Array.from( select.options );
	context.items = options
		.filter( ( option ) => option.value !== '' )
		.map( ( option ) => ( {
			value: option.value,
			label: option.textContent?.trim() ?? option.value,
		} ) );
	context.placeholder =
		options
			.find( ( option ) => option.value === '' )
			?.textContent?.trim() ?? '';
	context.value = select.value;
	context.hidden = select.hidden;
	context.disabled = select.disabled;
};

const { state } = store( 'axell/select', {
	state: {
		get shown(): string {
			const context = getContext< SelectContext >();
			const item = context.items.find(
				( option ) => option.value === context.value
			);
			if ( ! item ) {
				return context.placeholder || ' ';
			}
			return context.kind === 'uf' ? item.value : item.label;
		},
		get isPlaceholder(): boolean {
			const context = getContext< SelectContext >();
			return ! context.items.some(
				( option ) => option.value === context.value
			);
		},
		get filtered(): Item[] {
			return filtered( getContext< SelectContext >() );
		},
		get hasResults(): boolean {
			return filtered( getContext< SelectContext >() ).length > 0;
		},
		get isUf(): boolean {
			return getContext< SelectContext >().kind === 'uf';
		},
		get itemLabel(): string {
			const context = getContext< SelectContext >();
			return context.item ? textOf( context, context.item ) : '';
		},
		get activeId(): string {
			const context = getContext< SelectContext >();
			return context.open && context.active >= 0
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
		toggle() {
			const context = getContext< SelectContext >();
			const box = wrapper();
			if ( context.disabled ) {
				return;
			}
			if ( context.open ) {
				close( context, box );
				return;
			}
			// The options as they are now (another store may have changed them).
			const select = nativeOf( box );
			if ( select ) {
				read( context, select );
			}
			context.open = true;
			context.query = '';
			context.active = context.items.findIndex(
				( item ) => item.value === context.value
			);
			// The element now: getElement() has no scope in a timeout.
			setTimeout( () => {
				(
					box?.querySelector< HTMLElement >( '.aa-select__search' ) ??
					box?.querySelector< HTMLElement >( '[role="listbox"]' )
				)?.focus();
			}, 0 );
			scrollActive( box );
		},

		onSearch( event: Event ) {
			const context = getContext< SelectContext >();
			context.query = ( event.target as HTMLInputElement ).value;
			context.active = filtered( context ).length ? 0 : -1;
		},

		onKeydown( event: KeyboardEvent ) {
			const context = getContext< SelectContext >();
			const box = wrapper();
			const target = event.target as HTMLElement;
			const onToggle = target.classList.contains( 'aa-select__toggle' );
			// Keys handled here are not the stores' around (a reseller panel).
			const handled = () => {
				event.preventDefault();
				event.stopPropagation();
			};
			// Typing on the button opens the list and starts the filter.
			if (
				onToggle &&
				context.search &&
				event.key.length === 1 &&
				event.key !== ' ' &&
				! event.ctrlKey &&
				! event.metaKey &&
				! event.altKey
			) {
				handled();
				if ( ! context.open ) {
					toggleOf( box )?.click();
				}
				context.query += event.key;
				context.active = filtered( context ).length ? 0 : -1;
				return;
			}
			if ( onToggle && ! context.open ) {
				if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
					handled();
					toggleOf( box )?.click();
				}
				return;
			}
			if ( ! context.open ) {
				return;
			}
			const list = filtered( context );
			switch ( event.key ) {
				case 'ArrowDown':
					handled();
					if ( list.length ) {
						context.active = ( context.active + 1 ) % list.length;
					}
					break;
				case 'ArrowUp':
					handled();
					if ( list.length ) {
						context.active =
							context.active <= 0
								? list.length - 1
								: context.active - 1;
					}
					break;
				case 'Enter':
				case ' ':
					// Space types in the filter; it chooses in a list without one.
					if ( event.key === ' ' && context.search ) {
						return;
					}
					// Never submit the form from the list.
					handled();
					if ( list[ context.active ] ) {
						choose( context, box, list[ context.active ] );
					}
					break;
				case 'Escape':
					handled();
					close( context, box );
					break;
				case 'Tab':
					context.open = false;
					context.active = -1;
					context.query = '';
					break;
			}
			scrollActive( box );
		},

		pick( event: MouseEvent ) {
			// Mousedown, before the filter loses the focus.
			event.preventDefault();
			const context = getContext< SelectContext >();
			if ( context.item ) {
				choose( context, wrapper(), context.item );
			}
		},

		onFocusOut( event: FocusEvent ) {
			const next = event.relatedTarget as Node | null;
			const box = event.currentTarget as HTMLElement;
			if ( ! next || ! box.contains( next ) ) {
				const context = getContext< SelectContext >();
				context.open = false;
				context.active = -1;
				context.query = '';
			}
		},
	},
	callbacks: {
		init() {
			const context = getContext< SelectContext >();
			const box = getElement().ref as HTMLElement;
			const select = nativeOf( box );
			const toggle = toggleOf( box );
			if ( ! select || ! toggle ) {
				return;
			}
			read( context, select );

			// The button looks as the select (the block's or the page's styles).
			const looks = getComputedStyle( select );
			LOOKS.forEach( ( property ) =>
				toggle.style.setProperty(
					property,
					looks.getPropertyValue( property )
				)
			);
			// The filter looks as the form's text fields: their classes (with
			// the block's styles, focus included) and their own style.
			const filter =
				box.querySelector< HTMLInputElement >( '.aa-select__search' );
			const field = select.form?.querySelector< HTMLInputElement >(
				'input[type="text"][class]:not([hidden]):not([role="combobox"])'
			);
			if ( filter && field ) {
				filter.classList.add( ...Array.from( field.classList ) );
				const style = field.getAttribute( 'style' );
				if ( style ) {
					filter.setAttribute( 'style', style );
				}
			}
			// Named by the select's label.
			const label = select.id
				? box.ownerDocument.querySelector< HTMLLabelElement >(
						`label[for="${ CSS.escape( select.id ) }"]`
					)
				: null;
			if ( label ) {
				label.id = label.id || `${ select.id }-label`;
				toggle.setAttribute(
					'aria-labelledby',
					`${ label.id } ${ toggle.id }`
				);
				box.querySelector( '[role="listbox"]' )?.setAttribute(
					'aria-labelledby',
					label.id
				);
			} else if ( select.getAttribute( 'aria-label' ) ) {
				toggle.setAttribute(
					'aria-label',
					select.getAttribute( 'aria-label' ) as string
				);
			}

			// Another script changes the select: its value, options or state.
			const sync = withScope( () =>
				read( getContext< SelectContext >(), select )
			);
			select.addEventListener( 'change', sync );
			const observer = new MutationObserver( sync );
			observer.observe( select, {
				childList: true,
				attributes: true,
				attributeFilter: [ 'hidden', 'disabled' ],
			} );
			// The browser's "fill in this field" focuses the select: the button.
			const toButton = () => toggle.focus();
			select.addEventListener( 'focus', toButton );
			context.ready = true;
			return () => {
				observer.disconnect();
				select.removeEventListener( 'change', sync );
				select.removeEventListener( 'focus', toButton );
			};
		},
	},
} );

export { state };
