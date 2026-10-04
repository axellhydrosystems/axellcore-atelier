import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormSubmissionNotificationAttributes } from './types';

/**
 * Save output before the Interactivity state (v1: classes and data-aac-*).
 * Kept so content saved with it stays valid; re-saving migrates it.
 *
 * @param root0
 * @param root0.attributes
 */
export function v1Save( {
	attributes,
}: BlockSaveProps< FormSubmissionNotificationAttributes > ) {
	const blockProps = useBlockProps.save( {
		className: [ 'aac-notice', `aac-notice-${ attributes.type }` ].join(
			' '
		),
	} );
	const innerBlocksProps = useInnerBlocksProps.save( {
		...blockProps,
		'data-aac-notice-type': attributes.type,
	} );
	return <div { ...innerBlocksProps } />;
}
