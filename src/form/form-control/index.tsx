import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import deprecated from './deprecated';
import icon from './icon';
import type { FormControlAttributes } from './types';
// Not imported here: block.json's editorScript array lists variations.js as
// its own independent entry — @wordpress/scripts compiles it separately
// from block.json's own reference, so importing it here too would make the
// browser load both bundles and double-register every variation.
import './style.scss';

registerBlockType< FormControlAttributes >( metadata.name, {
	...metadata,
	icon,
	edit: Edit,
	save,
	deprecated,
} as unknown as Parameters< typeof registerBlockType< FormControlAttributes > >[ 1 ] );
