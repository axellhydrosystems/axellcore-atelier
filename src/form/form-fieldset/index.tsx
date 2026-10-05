import { registerBlockType } from '@wordpress/blocks';
import { group as groupIcon } from '@wordpress/icons';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import type { FormFieldsetAttributes } from './types';
import './style.scss';

registerBlockType< FormFieldsetAttributes >( metadata.name, {
	...metadata,
	icon: groupIcon,
	edit: Edit,
	save,
} );
