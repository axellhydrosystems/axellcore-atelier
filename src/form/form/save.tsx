import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import type { FormAttributes } from './types';

/** admin-post action of the no-JavaScript submission (includes/class-members.php). */
export const ADMIN_ACTION = 'axellcore_member_submit';

/**
 * Interactivity wiring for a REST-submitting form. The status lives in the
 * form's context; the notices read it (see view.ts). Without JavaScript the
 * form posts to admin-post.php (action and method are added on render).
 *
 * @param attributes Block attributes.
 * @param extra      Props already built by the block (block supports).
 * @return Form props.
 */
function formProps(
	attributes: FormAttributes,
	extra: Record< string, unknown >
) {
	return {
		...( attributes.submitsToRest
			? {
					noValidate: true,
					'data-wp-interactive': 'axell/form',
					'data-wp-context': '{"status":"idle"}',
					'data-wp-on--submit': 'actions.submit',
				}
			: {} ),
		...extra,
	};
}

export default function save( {
	attributes,
}: BlockSaveProps< FormAttributes > ) {
	const blockProps = useBlockProps.save();
	const { children, ...innerBlocksProps } = useInnerBlocksProps.save(
		formProps( attributes, blockProps )
	) as Record< string, unknown > & { children: React.ReactNode };

	if ( ! attributes.submitsToRest ) {
		return <form { ...innerBlocksProps }>{ children }</form>;
	}

	return (
		<form { ...innerBlocksProps }>
			<input type="hidden" name="action" value={ ADMIN_ACTION } />
			{ /* Honeypot: hidden from people, filled in by bots. */ }
			<div hidden>
				<input
					type="text"
					name="website"
					tabIndex={ -1 }
					autoComplete="off"
					aria-label={ __(
						'Leave this field empty',
						'axellcore-atelierclub'
					) }
				/>
			</div>
			{ children }
		</form>
	);
}
