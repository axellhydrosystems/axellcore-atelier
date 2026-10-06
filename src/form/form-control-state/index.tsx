import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import icon from '../form-control/icon';

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	icon,
	edit: Edit,
	save,
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
