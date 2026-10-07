/*
 * v1: the save with the Interactivity directives in the markup (before they
 * moved to render time, includes/class-form-directives.php). Saved blocks with
 * them validate here and are re-saved without them.
 *
 * v2: the whole widget in a wrapper div, before the save kept only the field
 * (the render builds the rest).
 */
import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import metadata from './block.json';
import { stateMarkup } from '../form-address/legacy-markup';
import type { AddressAttributes } from '../form-address/attributes';
import { wrappedStateMarkup } from '../form-address/wrapped-markup';

function v1Save( { attributes }: BlockSaveProps< AddressAttributes > ) {
	const blockProps = useBlockProps.save();
	return stateMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, countryField: attributes.countryField as string, countrySource: attributes.countrySource as string, country: attributes.country as string } );
}

function v2Save( { attributes }: BlockSaveProps< AddressAttributes > ) {
	const blockProps = useBlockProps.save();
	return wrappedStateMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, countryField: attributes.countryField as string, countrySource: attributes.countrySource as string, country: attributes.country as string } );
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
