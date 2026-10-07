import { store, getContext, getElement, withScope } from '@wordpress/interactivity';
import { guessType, isValidCNPJ, isValidCPF, mask, PLACEHOLDERS } from './document';
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

store( 'axell/document', {
	state: {
		get placeholder(): string {
			const context = getContext< DocumentContext >();
			return context.type ? PLACEHOLDERS[ context.type ] : context.placeholder || PLACEHOLDERS[ '' ];
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
			input.value = mask( input.value, context.type || guessType( input.value ) );
			input.setCustomValidity( '' );
		},

		/** Real check-digit validation, reported through the input's validity. */
		validate( event: Event ) {
			const context = getContext< DocumentContext >();
			const input = event.target as HTMLInputElement;
			if ( ! input.value.trim() ) {
				input.setCustomValidity( '' );
				return;
			}
			const type = context.type || guessType( input.value );
			const valid = type === 'cnpj' ? isValidCNPJ( input.value ) : isValidCPF( input.value );
			input.setCustomValidity( valid ? '' : type === 'cnpj' ? 'CNPJ inválido.' : 'CPF inválido.' );
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
			const field = form?.elements.namedItem( context.typeField ) as HTMLSelectElement | null;
			if ( ! field || ! ( 'value' in field ) ) {
				return;
			}
			// The region is the input itself (older saves wrapped it in a div).
			const input = region instanceof HTMLInputElement ? region : region.querySelector< HTMLInputElement >( 'input' );
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
