/**
 * Partner stores on the user edit screens (includes/class-member-profile.php):
 * up to five positions, each a search over the registered resellers. Only a
 * reseller from the list fills a position (no store typed by hand); the next
 * position shows once the one before has a store, as on the form.
 */
import { store, getContext } from '@wordpress/interactivity';
import './fields';
import './select';

interface Option {
	id: number;
	label: string;
}

interface Slot {
	id: string;
	title: string;
	pending: boolean;
	url: string;
	options: Option[];
	open: boolean;
	active: number;
	/** The server found this position wrong (until it is edited). */
	invalid: boolean;
	query?: string;
}

interface StoresContext {
	slots: Slot[];
	index: number;
	option?: Option;
}

interface ServerState {
	optionsUrl: string;
	postType: string;
	template: string;
}

const DEBOUNCE_MS = 250;

const slotOf = (): Slot => {
	const context = getContext< StoresContext >();
	return context.slots[ context.index ];
};

const choose = ( slot: Slot, option: Option ) => {
	slot.id = String( option.id );
	slot.title = option.label;
	slot.query = option.label;
	slot.pending = false;
	slot.open = false;
	slot.active = -1;
};

const close = ( slot: Slot ) => {
	slot.open = false;
	slot.active = -1;
};

const { state } = store( 'axell/member-stores', {
	state: {
		get isHidden(): boolean {
			const { slots, index } = getContext< StoresContext >();
			return (
				index > 0 &&
				! slots[ index ].title &&
				! slots[ index - 1 ].title
			);
		},
		get title(): string {
			return slotOf().title;
		},
		get id(): string {
			return slotOf().id;
		},
		get options(): Option[] {
			return slotOf().options;
		},
		get isOpen(): boolean {
			const slot = slotOf();
			return slot.open && slot.options.length > 0;
		},
		get isPending(): boolean {
			return slotOf().pending;
		},
		get isInvalid(): boolean {
			return slotOf().invalid;
		},
		get isFilled(): boolean {
			return slotOf().title !== '';
		},
		get activeId(): string {
			const { index } = getContext< StoresContext >();
			const slot = slotOf();
			return slot.open && slot.active >= 0
				? `aa_member_resellers_${ index }-option-${ slot.active }`
				: '';
		},
		get optionId(): string {
			const { index, option } = getContext< StoresContext >();
			const position = slotOf().options.findIndex(
				( item ) => item.id === option?.id
			);
			return `aa_member_resellers_${ index }-option-${ position }`;
		},
		get isActive(): boolean {
			const { option } = getContext< StoresContext >();
			const slot = slotOf();
			return slot.options[ slot.active ]?.id === option?.id;
		},
	},
	actions: {
		*onInput( event: Event ): Generator< unknown, void, unknown > {
			const slot = slotOf();
			const query = ( event.target as HTMLInputElement ).value;

			// Typing drops the chosen reseller until one is picked again.
			slot.title = query;
			slot.query = query;
			slot.id = '';
			slot.pending = false;
			slot.invalid = false;
			slot.active = -1;
			if ( ! query.trim() ) {
				slot.options = [];
				slot.open = false;
				return;
			}

			// Debounce: a newer keystroke makes this request stale.
			yield new Promise( ( resolve ) =>
				setTimeout( resolve, DEBOUNCE_MS )
			);
			if ( slot.query !== query ) {
				return;
			}

			const server = state as unknown as ServerState;
			const url = new URL( server.optionsUrl );
			url.searchParams.set( 'post_type', server.postType );
			url.searchParams.set( 'template', server.template );
			url.searchParams.set( 'q', query.trim() );
			try {
				const response = ( yield fetch( url.toString() ) ) as Response;
				if ( ! response.ok ) {
					throw new Error( response.statusText );
				}
				const options = ( yield response.json() ) as Option[];
				if ( slot.query === query ) {
					slot.options = options;
					slot.open = true;
				}
			} catch {
				if ( slot.query === query ) {
					slot.options = [];
					slot.open = false;
				}
			}
		},

		onKeydown( event: KeyboardEvent ) {
			const slot = slotOf();
			const total = slot.options.length;
			switch ( event.key ) {
				case 'ArrowDown':
					event.preventDefault();
					if ( total ) {
						slot.open = true;
						slot.active = ( slot.active + 1 ) % total;
					}
					break;
				case 'ArrowUp':
					event.preventDefault();
					if ( total ) {
						slot.open = true;
						slot.active =
							slot.active <= 0 ? total - 1 : slot.active - 1;
					}
					break;
				case 'Enter':
					// Never submit the profile from the search field.
					event.preventDefault();
					if ( slot.open && slot.options[ slot.active ] ) {
						choose( slot, slot.options[ slot.active ] );
					}
					break;
				case 'Escape':
					if ( slot.open ) {
						event.preventDefault();
						close( slot );
					}
					break;
			}
		},

		pick( event: MouseEvent ) {
			// Mousedown, before the field loses the focus and closes the list.
			event.preventDefault();
			const { option } = getContext< StoresContext >();
			if ( option ) {
				choose( slotOf(), option );
			}
		},

		onFocusOut( event: FocusEvent ) {
			const wrapper = event.currentTarget as HTMLElement;
			const next = event.relatedTarget as Node | null;
			if ( ! next || ! wrapper.contains( next ) ) {
				close( slotOf() );
			}
		},

		remove() {
			const slot = slotOf();
			slot.id = '';
			slot.title = '';
			slot.query = '';
			slot.pending = false;
			slot.invalid = false;
			slot.options = [];
			close( slot );
		},
	},
} );
