import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';
import './style.scss';
import icon from '../form-control/icon';

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	icon,
	edit: Edit,
	save,
	deprecated,
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
