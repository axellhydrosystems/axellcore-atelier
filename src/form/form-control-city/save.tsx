import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import { cityMarkup } from '../form-address/markup';
import type { AddressAttributes } from '../form-address/attributes';

export default function save( { attributes }: BlockSaveProps< AddressAttributes > ) {
	const blockProps = useBlockProps.save();
	return cityMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, stateField: attributes.stateField as string, searchable: !! attributes.searchable, countryField: attributes.countryField as string } );
}
