import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from '../form/save';
import icon from '../form/icon';
import { v3Save } from '../form/deprecated';

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	icon,
	edit: Edit,
	save,
	deprecated: [ { attributes: metadata.attributes, supports: metadata.supports, save: v3Save } ],
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
