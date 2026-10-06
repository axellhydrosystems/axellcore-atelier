import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { ADDRESS_CONTEXT } from './context';

export default function save() {
	const blockProps = useBlockProps.save( {
		'data-wp-interactive': 'axell/address',
		'data-wp-context': JSON.stringify( ADDRESS_CONTEXT ),
	} );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	return <div { ...innerBlocksProps } />;
}
