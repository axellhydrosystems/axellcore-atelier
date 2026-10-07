import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormSubmissionNotificationAttributes } from './types';

/**
 * Save output before the Interactivity state (v1: classes and data-aa-*).
 * Kept so content saved with it stays valid; re-saving migrates it.
 *
 * @param root0
 * @param root0.attributes
 */
export function v1Save( {
	attributes,
}: BlockSaveProps< FormSubmissionNotificationAttributes > ) {
	const blockProps = useBlockProps.save( {
		className: [ 'aa-notice', `aa-notice-${ attributes.type }` ].join(
			' '
		),
	} );
	const innerBlocksProps = useInnerBlocksProps.save( {
		...blockProps,
		'data-aa-notice-type': attributes.type,
	} );
	return <div { ...innerBlocksProps } />;
}

/**
 * Save output with the visibility directive in the markup (v2), before it
 * moved to render time (includes/class-form-directives.php).
 *
 * @param root0
 * @param root0.attributes
 */
export function v2Save( {
	attributes,
}: BlockSaveProps< FormSubmissionNotificationAttributes > ) {
	const blockProps = useBlockProps.save( {
		hidden: true,
		'data-axell-notice-type': attributes.type,
		'data-wp-bind--hidden': attributes.type === 'error' ? '!state.isError' : '!state.isSuccess',
	} );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	return <div { ...innerBlocksProps } />;
}
