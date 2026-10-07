/*
 * v1: the save with the autocomplete's Interactivity directives in the markup
 * (before they moved to render time, includes/class-form-directives.php).
 * The other control types save the same markup as now.
 */
import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import metadata from './block.json';
import type { FormControlAttributes } from './types';
import ControlElement from './control-element';
import { autocompleteMarkup as legacyAutocompleteMarkup } from './legacy-autocomplete-markup';

function v1Save( { attributes }: BlockSaveProps< FormControlAttributes > ) {
	const blockProps = useBlockProps.save();
	return ControlElement( attributes, true, () => undefined, blockProps, legacyAutocompleteMarkup );
}

export default [
	{
		attributes: metadata.attributes,
		supports: metadata.supports,
		save: v1Save,
	},
];
