import { useBlockProps, useInnerBlocksProps, RichText } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import metadata from './block.json';
import type { FormFieldsetAttributes } from './types';

/**
 * v1: the legend carried an `aa-form-legend-text` class, also used as the
 * attribute's selector. Saved fieldsets with it still validate and are
 * re-saved with a plain <legend>.
 */
const v1 = {
	attributes: {
		...metadata.attributes,
		legend: { type: 'rich-text', source: 'rich-text', selector: '.aa-form-legend-text', default: '' },
	},
	supports: metadata.supports,
	save( { attributes }: BlockSaveProps< FormFieldsetAttributes > ) {
		const blockProps = useBlockProps.save();
		const innerBlocksProps = useInnerBlocksProps.save( blockProps );
		const hasLegend = attributes.legend && attributes.legend.replace( /<[^>]+>/g, '' ).trim();
		return (
			<fieldset { ...innerBlocksProps }>
				{ hasLegend ? (
					<RichText.Content tagName="legend" className="aa-form-legend-text" value={ attributes.legend } />
				) : null }
				{ innerBlocksProps.children as React.ReactNode }
			</fieldset>
		);
	},
};

export default [ v1 ];
