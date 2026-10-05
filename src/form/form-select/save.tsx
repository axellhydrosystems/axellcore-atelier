import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormSelectAttributes } from './types';
import SelectElement from './element';

export default function save( { attributes }: BlockSaveProps< FormSelectAttributes > ) {
	const blockProps = useBlockProps.save();
	return SelectElement( attributes, blockProps );
}
