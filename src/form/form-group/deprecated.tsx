import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import metadata from './block.json';

type V1Attributes = { fieldWidth: string; style?: Record< string, unknown > };

/** Twelfth of the old Largura slider, as a grid column span. */
const TWELFTH = 100 / 12;

/**
 * v1: the field carried its own width (`fieldWidth`, a twelfth from the
 * Largura slider or a custom value), saved as an inline `width`. The layout
 * now comes from a core/group grid in the fieldset: a % width becomes the
 * core grid span (`style.layout.columnSpan`), other units are dropped.
 */
const v1 = {
	attributes: { fieldWidth: { type: 'string', default: '' } },
	supports: {
		...metadata.supports,
		spacing: { ...metadata.supports.spacing, blockGap: true },
	},
	save( { attributes }: BlockSaveProps< V1Attributes > ) {
		const blockProps = useBlockProps.save( {
			style: attributes.fieldWidth ? { width: attributes.fieldWidth } : undefined,
		} );
		return <div { ...useInnerBlocksProps.save( blockProps ) } />;
	},
	migrate( { fieldWidth, ...attributes }: V1Attributes ) {
		const pct = /^(\d*\.?\d+)%$/.exec( ( fieldWidth || '' ).trim() );
		if ( ! pct ) {
			return attributes;
		}
		const span = Math.min( 12, Math.max( 1, Math.round( parseFloat( pct[ 1 ] ) / TWELFTH ) ) );
		const style = attributes.style ?? {};
		return {
			...attributes,
			style: {
				...style,
				layout: { ...( style.layout as object | undefined ), columnSpan: span },
			},
		};
	},
};

export default [ v1 ];
