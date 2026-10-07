/*
 * v1: the save with the Interactivity directives in the markup (before they
 * moved to render time, includes/class-form-directives.php). Saved blocks with
 * them validate here and are re-saved without them.
 */
import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import metadata from './block.json';
import { autocompleteMarkup } from '../form-control/legacy-autocomplete-markup';
import { RESELLER_POST_TYPE, RESELLER_TEMPLATE } from './constants';

function v1Save( { attributes }: BlockSaveProps< ResellerAttributes > ) {
	const blockProps = useBlockProps.save();
	return autocompleteMarkup( {
		blockProps,
		isSave: true,
		id: attributes.id || undefined,
		name: attributes.name || attributes.id,
		placeholder: attributes.placeholder || undefined,
		postType: RESELLER_POST_TYPE,
		template: RESELLER_TEMPLATE,
		allowNotFound: !! attributes.allowNotFound,
	} );
}

export interface ResellerAttributes {
	id: string;
	name: string;
	placeholder: string;
	required: boolean;
	allowNotFound: boolean;
	[ key: string ]: unknown;
}

export default [
	{
		attributes: metadata.attributes,
		supports: metadata.supports,
		save: v1Save,
	},
];
