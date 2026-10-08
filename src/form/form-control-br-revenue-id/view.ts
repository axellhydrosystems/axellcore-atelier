import {
	store,
	getContext,
	getElement,
	withScope,
} from '@wordpress/interactivity';
import {
	guessType,
	isValidCNPJ,
	isValidCPF,
	mask,
	PLACEHOLDERS,
} from './document';
import type { DocType } from './document';

/**
 * Document type from the linked type field's value: `cpf` / `individual`
 * (pessoa física) or `cnpj` / `legal_entity` (pessoa jurídica).
 * @param value
 */
const docTypeOf = ( value: string ): DocType => {
	const v = value.trim().toLowerCase();
	if ( v === 'cpf' || v === 'individual' ) {
		return 'cpf';
	}
	return v === 'cnpj' || v === 'legal_entity' ? 'cnpj' : '';
};

interface DocumentContext {
	/** cpf or cnpj: fixed by the block, or chosen in its PF/PJ switch. '' = by length. */
	type: DocType;
	/** The type is fixed (CPF or CNPJ block), not chosen. */
	fixed: boolean;
	/** Placeholder of the block for the "by length" case. */
	placeholder: string;
	/** Name of the field that sets the type (cpf or cnpj), in the same form. */
	typeField: string;
}

/**
 * Set by includes/class-form-block.php (wp_interactivity_state): the REST
 * route that tells whether a document is registered, and translated messages.
 */
interface DocumentServerState {
	documentUrl: string;
	invalidCpf: string;
	invalidCnpj: string;
	registered: string;
}
const serverState = (): DocumentServerState =>
	state as unknown as DocumentServerState;

const { state } = store( 'axell/document', {
	state: {
		get placeholder(): string {
			const context = getContext< DocumentContext >();
			return context.type
				? PLACEHOLDERS[ context.type ]
				: context.placeholder || PLACEHOLDERS[ '' ];
		},
		get maxLength(): number {
			const type = getContext< DocumentContext >().type;
			return type === 'cpf' ? 14 : 18;
		},
	},
	actions: {
		onInput( event: Event ) {
			const context = getContext< DocumentContext >();
			const input = event.target as HTMLInputElement;
			input.value = mask(
				input.value,
				context.type || guessType( input.value )
			);
			input.setCustomValidity( '' );
		},

		/**
		 * Check digits, then whether it is already registered (the server
		 * checks both again on submit), reported through the input's validity.
		 * @param event
		 */
		*validate( event: Event ): Generator< unknown, void, unknown > {
			const input = event.target as HTMLInputElement;
			const value = input.value.trim();
			if ( ! value ) {
				input.setCustomValidity( '' );
				return;
			}
			const type =
				getContext< DocumentContext >().type || guessType( value );
			const valid =
				type === 'cnpj' ? isValidCNPJ( value ) : isValidCPF( value );
			const server = serverState();
			if ( ! valid ) {
				input.setCustomValidity(
					type === 'cnpj' ? server.invalidCnpj : server.invalidCpf
				);
				return;
			}
			input.setCustomValidity( '' );
			if ( ! server.documentUrl ) {
				return;
			}
			try {
				const url = new URL( server.documentUrl );
				url.searchParams.set( 'value', value );
				url.searchParams.set( 'type', type );
				const response = ( yield fetch( url.toString() ) ) as Response;
				const result = response.ok
					? ( ( yield response.json() ) as { exists?: boolean } )
					: {};
				// Only for the value checked: the visitor may have typed since.
				if ( result.exists && input.value.trim() === value ) {
					input.setCustomValidity( server.registered );
				}
			} catch {
				// Offline or rate limited: the server checks again on submit.
			}
		},
	},
	callbacks: {
		/**
		 * Follow the linked type field (e.g. a "Tipo de cadastro" select): its
		 * value sets the type (see docTypeOf), and a new type clears the document.
		 */
		linkType() {
			const context = getContext< DocumentContext >();
			if ( context.fixed || ! context.typeField ) {
				return;
			}
			const region = getElement().ref as HTMLElement;
			const form = region.closest( 'form' );
			const field = form?.elements.namedItem(
				context.typeField
			) as HTMLSelectElement | null;
			if ( ! field || ! ( 'value' in field ) ) {
				return;
			}
			// The region is the input itself (older saves wrapped it in a div).
			const input =
				region instanceof HTMLInputElement
					? region
					: region.querySelector< HTMLInputElement >( 'input' );
			const apply = withScope( () => {
				const ctx = getContext< DocumentContext >();
				ctx.type = docTypeOf( field.value );
			} );
			apply();
			field.addEventListener( 'change', () => {
				apply();
				if ( input ) {
					input.value = '';
					input.setCustomValidity( '' );
				}
			} );
		},
	},
} );
