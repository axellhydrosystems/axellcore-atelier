import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormFieldsetAttributes } from './types';

export default function save( {
	attributes,
}: BlockSaveProps< FormFieldsetAttributes > ) {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	const hasLegend =
		attributes.legend && attributes.legend.replace( /<[^>]+>/g, '' ).trim();

	return (
		<fieldset { ...innerBlocksProps }>
			{ hasLegend ? (
				<RichText.Content
					tagName="legend"
					className="aa-form-legend-text"
					value={ attributes.legend }
				/>
			) : null }
			{ innerBlocksProps.children as React.ReactNode }
		</fieldset>
	);
}
