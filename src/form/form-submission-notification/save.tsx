import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormSubmissionNotificationAttributes } from './types';

/**
 * Starts hidden. The form's Interactivity store (src/form/form/view.ts) shows
 * the notice that matches the submission result, through data-wp-bind--hidden.
 * No class is added here: the look comes from the block's own supports and the
 * stylesheet, keyed on the wrapper and on data-axell-notice-type.
 *
 * @param root0
 * @param root0.attributes
 */
export default function save( {
	attributes,
}: BlockSaveProps< FormSubmissionNotificationAttributes > ) {
	const blockProps = useBlockProps.save( {
		hidden: true,
		'data-axell-notice-type': attributes.type,
		'data-wp-bind--hidden':
			attributes.type === 'error' ? '!state.isError' : '!state.isSuccess',
	} );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	return <div { ...innerBlocksProps } />;
}
