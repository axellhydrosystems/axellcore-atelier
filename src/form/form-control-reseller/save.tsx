import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import { autocompleteMarkup } from '../form-control/autocomplete-markup';
import { RESELLER_POST_TYPE, RESELLER_TEMPLATE } from './constants';

export default function save( { attributes }: BlockSaveProps< ResellerAttributes > ) {
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
