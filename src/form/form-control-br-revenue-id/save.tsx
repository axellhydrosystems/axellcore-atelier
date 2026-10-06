import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import { documentMarkup, type BrDocumentAttributes } from './markup';

export default function save( { attributes }: BlockSaveProps< BrDocumentAttributes > ) {
	return documentMarkup( attributes, useBlockProps.save(), '' );
}
