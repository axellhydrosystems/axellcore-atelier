import { __ } from '@wordpress/i18n';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import './editor.scss';
import type { FormSubmissionNotificationAttributes } from './types';

const ALLOWED_BLOCKS = [ 'core/paragraph', 'core/heading', 'core/group' ];
const TEMPLATE: Array< [ string, Record< string, unknown > ] > = [
	[ 'core/paragraph', {} ],
];

function classesFor(
	attributes: FormSubmissionNotificationAttributes
): string {
	return [
		'aac-notice',
		`aac-notice-${ attributes.type }`,
		`form-notification-type-${ attributes.type }`,
	].join( ' ' );
}

/**
 * axell/form-submission-notification — an editable success/error message
 * inside axell/form. Replaces a blocking window.alert() (see
 * assets/js/frontend.js's submit handler) — that gap and the heritage of
 * this block (ported from Gutenberg's removed experimental
 * core/form-submission-notification, adapted for our fetch()-based,
 * no-page-reload submission) are documented in this plugin's own CLAUDE.md.
 *
 * Frontend visibility: `.aac-notice` starts hidden (assets/css/sections.css)
 * and frontend.js adds `.is-active` to the matching
 * `[data-aac-notice-type="success"|"error"]` element once its form's fetch()
 * resolves. In the editor, this hiding rule doesn't apply (no plugin CSS
 * loads inside the editor iframe — a separately tracked gap, see
 * CLAUDE.md), so both notifications render fully visible/editable here
 * without extra effort.
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 */
export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FormSubmissionNotificationAttributes > ) {
	const blockProps = useBlockProps( { className: classesFor( attributes ) } );
	const innerBlocksProps = useInnerBlocksProps(
		{
			...blockProps,
			'data-aac-notice-type': attributes.type,
			// Shown by the editor-only ::after overlay (form-editor.css), as in core.
			'data-message-success': __( 'Submission success notification', 'axellcore-atelierclub' ),
			'data-message-error': __( 'Submission error notification', 'axellcore-atelierclub' ),
		},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);

	return (
		<>
			<div { ...innerBlocksProps } />
		</>
	);
}
