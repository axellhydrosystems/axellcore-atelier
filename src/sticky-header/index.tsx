import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

/**
 * Dynamic block: the saved markup is only the inner blocks. The wrapper,
 * the Interactivity API directives and the layout classes come from
 * render.php on every request.
 */
registerBlockType( metadata.name, {
	...metadata,
	icon: 'sticky',
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} as Parameters< typeof registerBlockType >[ 1 ] );
