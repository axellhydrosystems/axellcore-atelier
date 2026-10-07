/*
 * v2 markup: a wrapper div around the plain field (the directives already on
 * render), before the field became the block root. Only the deprecations use it.
 */
import { PLACEHOLDERS } from './document';
import type { DocType } from './document';
import type { BrDocumentAttributes } from './markup';

const FIELD = 'wp-block-axell-form-control';

export function wrappedDocumentMarkup(
	attributes: BrDocumentAttributes,
	blockProps: Record< string, unknown >,
	fixedType: DocType = '',
	isEditor = false
) {
	const placeholder = attributes.placeholder || PLACEHOLDERS[ fixedType ];

	return (
		// The axell/document directives are added on render
		// (includes/class-form-directives.php).
		<div { ...blockProps }>
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
			/>
		</div>
	);
}
