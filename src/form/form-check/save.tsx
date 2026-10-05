import { useBlockProps, RichText } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormCheckAttributes } from './types';

export default function save( { attributes }: BlockSaveProps< FormCheckAttributes > ) {
	const blockProps = useBlockProps.save();
	const id = attributes.id || undefined;
	const name = attributes.name || attributes.id || undefined;

	return (
		<div { ...blockProps }>
			<input
				type="checkbox"
				id={ id }
				name={ name }
				required={ attributes.required || undefined }
				aria-required={ attributes.required || undefined }
				defaultChecked={ attributes.checked || undefined }
			/>
			<RichText.Content
				tagName="label"
				htmlFor={ id }
				className="wp-block-axell-form-label"
				value={ attributes.text }
			/>
		</div>
	);
}
