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
		'admin/dataviews/index': src( 'admin/dataviews/index.ts' ),
		'admin/members/index': src( 'admin/members/index.tsx' ),
		'admin/members-export/index': src( 'admin/members-export/index.ts' ),
		'admin/resellers/index': src( 'admin/resellers/index.tsx' ),
		'reveal/index': src( 'reveal/index.tsx' ),
		'reveal/frontend': src( 'reveal/style.scss' ),
		'inline-icon/index': src( 'inline-icon/index.tsx' ),
		'block-styles/frontend': src( 'block-styles/style.scss' ),
		'admin/member-profile/index': src( 'admin/member-profile/style.scss' ),
		'places/frontend': src( 'places/style.scss' ),
	},
	{
		'reveal/view': src( 'reveal/view.ts' ),
		'admin/member-profile/view': src( 'admin/member-profile/view.ts' ),
		'places/view': src( 'places/view.ts' ),
	},
];

/*
 * @wordpress/dataviews declares "sideEffects": false, so webpack drops its
 * stylesheet import and the admin list renders unstyled. Keep that file.
 */
const keepDataviewsStyles = {
	test: /@wordpress[\\/]dataviews[\\/]build-style[\\/]style\.css$/,
	sideEffects: true,
};

const withExtraEntries = ( config, extraEntries ) => {
	const defaultEntry = config.entry;
	return {
		...config,
		module: {
			...config.module,
			rules: [ keepDataviewsStyles, ...( config.module?.rules ?? [] ) ],
		},
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
