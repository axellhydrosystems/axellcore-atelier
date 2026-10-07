/*
 * v1: the save with the Interactivity directives in the markup (before they
 * moved to render time, includes/class-form-directives.php). Saved blocks with
 * them validate here and are re-saved without them.
 */
import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import metadata from './block.json';
import { wrappedDocumentMarkup } from './wrapped-markup';
import { documentMarkup, type BrDocumentAttributes } from './legacy-markup';

function v1Save( { attributes }: BlockSaveProps< BrDocumentAttributes > ) {
	return documentMarkup( attributes, useBlockProps.save(), '' );
}

/*
 * v2: a wrapper div around the plain field (directives already on render),
 * before the field became the block root.
 */
function v2Save( { attributes }: BlockSaveProps< BrDocumentAttributes > ) {
	return wrappedDocumentMarkup( attributes, useBlockProps.save(), '' );
}

export default [
	{
		attributes: metadata.attributes,
		supports: metadata.supports,
		save: v2Save,
	},
	{
		attributes: metadata.attributes,
		supports: metadata.supports,
		save: v1Save,
	},
];
