import { registerBlockType } from '@wordpress/blocks';
import type { BlockDeprecation } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import { v1Save } from './deprecated';
import type { FormSubmissionNotificationAttributes } from './types';

registerBlockType< FormSubmissionNotificationAttributes >( metadata.name, {
	...metadata,
	edit: Edit,
	save,
	deprecated: [
		{
			attributes: metadata.attributes,
			save: v1Save,
		} as unknown as BlockDeprecation< FormSubmissionNotificationAttributes >,
	],
} );
