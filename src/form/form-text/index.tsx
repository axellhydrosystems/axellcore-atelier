import { registerBlockType } from '@wordpress/blocks';
import { paragraph as paragraphIcon } from '@wordpress/icons';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	icon: paragraphIcon,
	edit: Edit,
	save,
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
