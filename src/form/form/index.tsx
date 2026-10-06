import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import icon from './icon';
import { v1Save, v2Save, migrateLegacy, LEGACY_ATTRIBUTES } from './deprecated';

const deprecated = [ v2Save, v1Save ].map( ( legacySave ) => ( {
	attributes: { ...LEGACY_ATTRIBUTES, className: { type: 'string' } },
	supports: metadata.supports,
	save: legacySave,
	migrate: migrateLegacy,
} ) );

registerBlockType( metadata.name, {
	...( metadata as unknown as Record< string, unknown > ),
	icon,
	edit: Edit,
	save,
	deprecated,
} as unknown as Parameters< typeof registerBlockType >[ 1 ] );
