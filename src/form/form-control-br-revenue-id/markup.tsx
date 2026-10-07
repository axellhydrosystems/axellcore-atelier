import { PLACEHOLDERS } from './document';
import type { DocType } from './document';

export interface BrDocumentAttributes {
	id: string;
	name: string;
	placeholder: string;
	required: boolean;
	/** Name of the field that sets the type (values cpf or cnpj); '' = by length. */
	typeField?: string;
	[ key: string ]: unknown;
}

/** Field look of the form controls (src/form/form-control/style.scss). */
const FIELD = 'wp-block-axell-form-control';

/**
 * Markup of a CPF/CNPJ control (the axell/document store, view.ts, wires it on
 * render), with an optional PF/PJ switch. fixedType makes it a CPF-only or a
 * CNPJ-only control.
 *
 * @param attributes Block attributes.
 * @param blockProps Block wrapper props.
 * @param fixedType  cpf or cnpj for the single-type blocks.
 * @param isEditor   Editor preview (fields read-only).
 */
export function documentMarkup(
	attributes: BrDocumentAttributes,
	blockProps: Record< string, unknown >,
	fixedType: DocType = '',
	isEditor = false
) {
	const placeholder = attributes.placeholder || PLACEHOLDERS[ fixedType ];

	// The field is the block root (no wrapper); the axell/document directives
	// are added to it on render (includes/class-form-directives.php).
	const className = [ blockProps.className as string | undefined, FIELD ].filter( Boolean ).join( ' ' );
	return (
		<input
			{ ...blockProps }
			className={ className }
			type="text"
			id={ attributes.id || undefined }
			name={ attributes.name || attributes.id || undefined }
			placeholder={ placeholder }
			readOnly={ isEditor || undefined }
			autoComplete="off"
			required={ attributes.required || undefined }
			aria-required={ attributes.required || undefined }
		/>
	);
}
