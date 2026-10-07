/*
 * Markup as saved before the Interactivity directives moved to render time
 * (includes/class-form-directives.php). Only the block deprecations use it, so
 * content saved with the directives still validates and migrates.
 */
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
 * Markup of a CPF/CNPJ control: its own region of the axell/document store
 * (view.ts), with an optional PF/PJ switch. fixedType makes it a CPF-only or a
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

	return (
		<div
			{ ...blockProps }
			data-wp-interactive="axell/document"
			data-wp-context={ JSON.stringify( {
				type: fixedType,
				fixed: !! fixedType,
				placeholder,
				typeField: fixedType ? '' : attributes.typeField || '',
			} ) }
			data-wp-init="callbacks.linkType"
		>
			<input
				type="text"
				id={ attributes.id || undefined }
				name={ attributes.name || attributes.id || undefined }
				className={ FIELD }
				placeholder={ placeholder }
				readOnly={ isEditor || undefined }
				autoComplete="off"
				required={ attributes.required || undefined }
				aria-required={ attributes.required || undefined }
				data-wp-bind--placeholder="state.placeholder"
				data-wp-bind--maxlength="state.maxLength"
				data-wp-on--input="actions.onInput"
				data-wp-on--blur="actions.validate"
			/>
		</div>
	);
}
