/*
 * v1: the save with the Interactivity directives in the markup (before they
 * moved to render time, includes/class-form-directives.php). Saved blocks with
 * them validate here and are re-saved without them.
 */
import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import metadata from './block.json';
import { wrappedPostalMarkup } from '../form-address/wrapped-markup';
import { postalMarkup } from '../form-address/legacy-markup';
import type { AddressAttributes } from '../form-address/attributes';

function v1Save( { attributes }: BlockSaveProps< AddressAttributes > ) {
	const blockProps = useBlockProps.save();
	return postalMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, countryField: attributes.countryField as string, countrySource: attributes.countrySource as string, country: attributes.country as string } );
}

/*
 * v2: a wrapper div around the plain field (directives already on render),
 * before the field became the block root.
 */
function v2Save( { attributes }: BlockSaveProps< AddressAttributes > ) {
	const blockProps = useBlockProps.save();
	return wrappedPostalMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, countryField: attributes.countryField as string, countrySource: attributes.countrySource as string, country: attributes.country as string } );
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
