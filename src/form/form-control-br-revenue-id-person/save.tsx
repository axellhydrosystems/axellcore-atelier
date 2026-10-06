import { useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import ControlElement from '../form-control/control-element';
import { controlAttributes, type BrDocumentAttributes } from '../form-control-br-revenue-id/control';

export default function save( { attributes }: BlockSaveProps< BrDocumentAttributes > ) {
	const blockProps = useBlockProps.save();
	return ControlElement( controlAttributes( attributes, 'cpf' ), true, () => undefined, blockProps );
}
