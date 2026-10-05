import { registerBlockType } from '@wordpress/blocks';
import { group as groupIcon } from '@wordpress/icons';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	icon: groupIcon,
	edit: Edit,
	save,
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
