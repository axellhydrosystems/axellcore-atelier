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
import { wrappedAutocompleteMarkup } from './wrapped-autocomplete-markup';

function v1Save( { attributes }: BlockSaveProps< FormControlAttributes > ) {
	const blockProps = useBlockProps.save();
	return ControlElement( attributes, true, () => undefined, blockProps, legacyAutocompleteMarkup );
}

/*
 * v2: the autocomplete widget saved whole without directives, before its save
 * became the search field only.
 */
function v2Save( { attributes }: BlockSaveProps< FormControlAttributes > ) {
	const blockProps = useBlockProps.save();
	return ControlElement( attributes, true, () => undefined, blockProps, wrappedAutocompleteMarkup );
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
