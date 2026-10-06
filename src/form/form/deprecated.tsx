import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

interface LegacyAttributes {
	submitsToRest: boolean;
	[ key: string ]: unknown;
}


/**
 * Save output before the Interactivity wiring (v1). Kept so content saved
 * with it stays valid in the editor; re-saving migrates it to the current save.
 * @param root0
 * @param root0.attributes
 */
export function v1Save( { attributes }: BlockSaveProps< LegacyAttributes > ) {
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save(
		attributes.submitsToRest
			? { ...blockProps, 'data-aa-club-form': '', noValidate: true }
			: blockProps
	);
	return <form { ...innerBlocksProps } />;
}

/**
 * Save output with the single "Destino do envio" switch (v2): the Interactivity
 * wiring, the members admin-post action and the honeypot only when submitsToRest.
 * @param root0
 * @param root0.attributes
 */
export function v2Save( { attributes }: BlockSaveProps< LegacyAttributes > ) {
	const blockProps = useBlockProps.save();
	const wired = attributes.submitsToRest
		? {
				noValidate: true,
				'data-wp-interactive': 'axell/form',
				'data-wp-context': '{"status":"idle"}',
				'data-wp-on--submit': 'actions.submit',
		  }
		: {};
	const { children, ...innerBlocksProps } = useInnerBlocksProps.save( {
		...wired,
		...blockProps,
	} ) as Record< string, unknown > & { children: React.ReactNode };

	if ( ! attributes.submitsToRest ) {
		return <form { ...innerBlocksProps }>{ children }</form>;
	}

	return (
		<form { ...innerBlocksProps }>
			<input type="hidden" name="action" value="axellcore_member_submit" />
			<div hidden>
				<input
					type="text"
					name="website"
					tabIndex={ -1 }
					autoComplete="off"
					aria-label={ __( 'Leave this field empty', 'axellcore-atelierclub' ) }
				/>
			</div>
			{ children }
		</form>
	);
}

/** "Membro (REST)" becomes "store into members". */
export function migrateLegacy( attributes: LegacyAttributes ) {
	const { submitsToRest, ...rest } = attributes;
	return { ...rest, storePostType: submitsToRest ? 'aa_member' : '' };
}

export const LEGACY_ATTRIBUTES = {
	submitsToRest: { type: 'boolean', default: false },
};
