const path = require( 'path' );
const defaultExport = require( '@wordpress/scripts/config/webpack.config' );

/*
 * With WP_EXPERIMENTAL_MODULES set (see the "build" script), wp-scripts exports
 * [ scriptConfig, moduleConfig ]. The module config compiles viewScriptModule
 * entries from block.json (src/sticky-header/view.ts) and the extra view
 * modules listed below.
 */
const defaultConfigs = Array.isArray( defaultExport )
	? defaultExport
	: [ defaultExport ];

const src = ( file ) => path.resolve( __dirname, 'src', file );

// Entries that are not driven by block.json, per config (index 0 = scripts).
const extraEntriesByConfig = [
	{
		'admin/members/index': src( 'admin/members/index.tsx' ),
		'reveal/index': src( 'reveal/index.tsx' ),
		'reveal/frontend': src( 'reveal/style.scss' ),
	},
	{
		'reveal/view': src( 'reveal/view.ts' ),
	},
];

const withExtraEntries = ( config, extraEntries ) => {
	const defaultEntry = config.entry;
	return {
		...config,
		entry: async () => ( {
			...( typeof defaultEntry === 'function'
				? await defaultEntry()
				: defaultEntry ),
			...extraEntries,
		} ),
	};
};

module.exports = defaultConfigs.map( ( config, index ) =>
	withExtraEntries( config, extraEntriesByConfig[ index ] ?? {} )
);
