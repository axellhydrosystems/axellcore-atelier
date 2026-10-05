import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import { widthStyle } from './width';

export default function save( { attributes }: BlockSaveProps< { fieldWidth: string } > ) {
	const blockProps = useBlockProps.save( { style: widthStyle( attributes.fieldWidth ) } );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	return <div { ...innerBlocksProps } />;
}
