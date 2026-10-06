import { useBlockProps, RichText } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormInputAttributes } from './types';
import { FieldControl, FieldWrapper } from './field-element';

export default function save( {
	attributes,
}: BlockSaveProps< FormInputAttributes > ) {
	const blockProps = useBlockProps.save();
	const labelEl = (
		<RichText.Content
			tagName="span"
			className="aa-field-label-text"
			value={ attributes.label }
		/>
	);
	return FieldWrapper(
		attributes,
		labelEl,
		FieldControl( attributes, true ),
		blockProps,
		true
	);
}
