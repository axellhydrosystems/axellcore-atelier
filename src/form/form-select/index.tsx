import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import icon from './icon';
import type { FormSelectAttributes } from './types';
import './style.scss';

registerBlockType< FormSelectAttributes >( metadata.name, {
	...metadata,
	icon,
	edit: Edit,
	save,
} );
