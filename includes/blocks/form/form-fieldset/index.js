/**
 * axell/form-fieldset — groups a set of related form fields. Static (no PHP
 * render), no build step (plain browser JS against wp.* globals). Replaces
 * the old `core/group.aa-form-row` + separate `.aa-form-legend`-styled
 * paragraph pattern with a real `<fieldset>`/`<legend>` pair.
 *
 * The legend is a toolbar toggle — "Add legend"/"Remove legend" — mirroring
 * core/image's own "Add caption"/"Remove caption" pattern exactly (a
 * `BlockControls` `ToolbarButton` driving local `showLegend` state,
 * clearing the `legend` attribute on removal). This is also *why* this
 * stays a real custom block rather than a `core/group` variation with
 * `tagName:"fieldset"` (see the two "Fieldset (plain)"/"Legend (plain)"
 * variations registered in axell/form/index.js for that simpler,
 * no-legend-concept alternative) — variations can't add stateful toolbar
 * behavior, only preset attributes/templates.
 *
 * Source strings are English; translations live in languages/*.po (see
 * bin/release.sh + `wp i18n make-pot`).
 */
( function ( blocks, element, blockEditor, components, richText, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useState = element.useState;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;
	var BlockControls = blockEditor.BlockControls;
	var RichText = blockEditor.RichText;
	var ToolbarButton = components.ToolbarButton;
	var __ = i18n.__;

	var ALLOWED_BLOCKS = [
		'axell/form-label',
		'axell/form-control',
		'core/paragraph',
		'core/heading',
		'core/group',
		'core/columns',
		'core/html',
	];

	var TEMPLATE = [ [ 'axell/form-label', {} ], [ 'axell/form-control', {} ] ];

	blocks.registerBlockType( 'axell/form-fieldset', {
		icon: 'editor-table',
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var innerBlocksProps = useInnerBlocksProps( blockProps, {
				allowedBlocks: ALLOWED_BLOCKS,
				template: TEMPLATE,
				templateInsertUpdatesSelection: false,
			} );

			var showLegendState = useState( !! attributes.legend );
			var showLegend = showLegendState[ 0 ];
			var setShowLegend = showLegendState[ 1 ];

			function toggleLegend() {
				if ( showLegend ) {
					setAttributes( { legend: '' } );
				}
				setShowLegend( ! showLegend );
			}

			var legendEl = showLegend
				? el( RichText, {
					tagName: 'legend',
					className: 'aa-form-legend-text',
					value: attributes.legend,
					onChange: function ( v ) {
						setAttributes( { legend: v } );
					},
					placeholder: __( 'Legend…', 'axellcore-atelierclub' ),
					allowedFormats: [],
				} )
				: null;

			return el(
				Fragment,
				null,
				el(
					BlockControls,
					{ group: 'block' },
					el( ToolbarButton, {
						icon: 'edit',
						label: showLegend ? __( 'Remove legend', 'axellcore-atelierclub' ) : __( 'Add legend', 'axellcore-atelierclub' ),
						isPressed: showLegend,
						onClick: toggleLegend,
					} )
				),
				el( 'fieldset', innerBlocksProps, legendEl, innerBlocksProps.children )
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save();
			var innerBlocksProps = useInnerBlocksProps.save( blockProps );
			var hasLegend = attributes.legend && attributes.legend.replace( /<[^>]+>/g, '' ).trim();
			var legendEl = hasLegend
				? el( RichText.Content, { tagName: 'legend', className: 'aa-form-legend-text', value: attributes.legend } )
				: null;

			return el(
				'fieldset',
				innerBlocksProps,
				legendEl,
				innerBlocksProps.children
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.richText, window.wp.i18n );
