import { __ } from '@wordpress/i18n';
import type { FormSelectAttributes } from './types';

/**
 * Builds the <select>, shared by edit() and save(). The root element IS the
 * select, so the block's own supports (color, typography, spacing) apply to
 * it directly, as in axell/form-control.
 * @param attributes
 * @param blockProps
 */
export default function SelectElement(
	attributes: FormSelectAttributes,
	blockProps: Record< string, unknown >
) {
	const { id, required, placeholder, options = [] } = attributes;
	const name = attributes.name || id || undefined;

	return (
		<select
			{ ...blockProps }
			id={ id || undefined }
			name={ name }
			required={ required || undefined }
			aria-required={ required || undefined }
		>
			<option value="">
				{ placeholder || __( 'Select an option', 'axellcore-atelier' ) }
			</option>
			{ options.map( ( o, i ) => (
				<option key={ i } value={ o.value }>
					{ o.label }
				</option>
			) ) }
		</select>
	);
}
