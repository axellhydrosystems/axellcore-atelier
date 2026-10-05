import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import icon from './icon';
import { v1Save } from './deprecated';
import type { BlockDeprecation } from '@wordpress/blocks';
import type { FormAttributes } from './types';

registerBlockType< FormAttributes >( metadata.name, {
	...metadata,
	icon,
	edit: Edit,
	save,
	deprecated: [
		{
			attributes: metadata.attributes,
			save: v1Save,
		} as unknown as BlockDeprecation< FormAttributes >,
	],
} );
