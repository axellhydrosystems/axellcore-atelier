import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { FormAttributes } from './types';

/**
 * Every form submits through the axell/form store (view.ts): with JavaScript to
 * the /submit route, without it to admin-post.php. The action, the post id, the
 * form id and the honeypot are added on render (includes/class-form-block.php),
 * and what the submission does comes from this block's settings on the server.
 *
 * @param root0
 * @param root0.attributes
 */
export default function save( { attributes }: BlockSaveProps< FormAttributes > ) {
	const blockProps = useBlockProps.save( {
		noValidate: true,
		'data-wp-interactive': 'axell/form',
		'data-wp-context': '{"status":"idle"}',
		'data-wp-on--submit': 'actions.submit',
		'data-form-id': attributes.formId || undefined,
	} );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	return <form { ...innerBlocksProps } />;
}
