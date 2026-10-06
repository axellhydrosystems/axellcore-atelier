import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import { countryMarkup } from '../form-address/markup';
import type { AddressAttributes } from '../form-address/attributes';

export default function save( { attributes }: BlockSaveProps< AddressAttributes > ) {
	const blockProps = useBlockProps.save();
	return countryMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, fixed: attributes.fixed as string, hiddenField: !! attributes.hiddenField } );
}
