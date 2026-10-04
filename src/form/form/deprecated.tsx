import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormAttributes } from './types';

/**
 * Save output before the Interactivity wiring (v1). Kept so content saved
 * with it stays valid in the editor; re-saving migrates it to the current save.
 * @param root0
 * @param root0.attributes
 */
export function v1Save( { attributes }: BlockSaveProps< FormAttributes > ) {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save(
		attributes.submitsToRest
			? { ...blockProps, 'data-aac-club-form': '', noValidate: true }
			: blockProps
	);
	return <form { ...innerBlocksProps } />;
}
