import { registerBlockType } from '@wordpress/blocks';
import { group as groupIcon } from '@wordpress/icons';
import './style.scss';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	icon: groupIcon,
	edit: Edit,
	save,
	deprecated,
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
