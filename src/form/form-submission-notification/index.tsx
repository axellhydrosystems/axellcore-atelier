import { registerBlockType } from '@wordpress/blocks';
import type { BlockDeprecation, BlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { group as groupIcon } from '@wordpress/icons';
import './style.scss';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import { v1Save } from './deprecated';
import type { FormSubmissionNotificationAttributes } from './types';

/**
 * Colour and padding of a notice, as in the saved markup of the variation:
 * a core/group with background and text colour, wrapping one paragraph.
 *
 * @param background Background colour.
 * @param text       Text colour.
 * @param message    Paragraph content.
 * @return innerBlocks entry for the variation.
 */
const notice = ( background: string, text: string, message: string ) => [
	'core/group',
	{
		style: {
			color: { background, text },
			elements: { link: { color: { text } } },
			spacing: {
				padding: {
					top: '1.25em',
					bottom: '1.25em',
					left: '2.375em',
					right: '2.375em',
				},
			},
		},
		layout: { type: 'constrained' },
	},
	[ [ 'core/paragraph', { content: message } ] ],
];

/**
 * Success and error are variations (as in core/form-submission-notification):
 * the inserter offers them, and the `type` attribute tells them apart.
 */
const variations = [
	{
		name: 'form-submission-success',
		title: __( 'Form Submission Success' ),
		description: __( 'Success message for form submissions.' ),
		icon: groupIcon,
		attributes: { type: 'success' },
		isDefault: true,
		scope: [ 'inserter', 'transform' ],
		isActive: ( attributes: { type?: string } ) =>
			! attributes?.type || attributes.type === 'success',
		innerBlocks: [
			notice(
				'#00d084',
				'#000000',
				__( 'Your form has been submitted successfully.', 'axellcore-atelierclub' )
			),
		],
	},
	{
		name: 'form-submission-error',
		title: __( 'Form Submission Error' ),
		description: __( 'Error/failure message for form submissions.' ),
		icon: groupIcon,
		attributes: { type: 'error' },
		isDefault: false,
		scope: [ 'inserter', 'transform' ],
		isActive: ( attributes: { type?: string } ) => attributes?.type === 'error',
		innerBlocks: [
			notice(
				'#cf2e2e',
				'#ffffff',
				__( 'There was an error submitting your form.', 'axellcore-atelierclub' )
			),
		],
	},
] as unknown as BlockVariation< FormSubmissionNotificationAttributes >[];

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	variations,
	edit: Edit,
	save,
	deprecated: [
		{
			attributes: metadata.attributes,
			save: v1Save,
		} as unknown as BlockDeprecation< FormSubmissionNotificationAttributes >,
	],
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
