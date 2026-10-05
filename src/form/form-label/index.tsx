import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import icon from './icon';
import type { FormLabelAttributes } from './types';
import './style.scss';

registerBlockType< FormLabelAttributes >( metadata.name, {
	...metadata,
	icon,
	edit: Edit,
	save,
} );
