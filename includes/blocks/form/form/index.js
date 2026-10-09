/**
 * axell/form — <form> wrapper block. Static (no PHP render). Clean/generic
 * by default (`<form class="wp-block-axell-form">`, nothing else baked
 * in) — the REST-submission wiring (`data-aa-club-form`/`noValidate`,
 * used by assets/js/frontend.js) is opt-in via the `submitsToRest`
 * attribute, and the branded look (`aa-apply-form`) comes from the
 * block's own `customClassName` support, same as any other block's
 * "Additional CSS class(es)" field — not hardcoded. This plugin's own
 * content (bin/generate-content.py) sets both explicitly; a fresh,
 * generic insert of this block elsewhere doesn't carry either.
 *
 * No build step — plain browser JS against the wp.* globals, same pattern
 * as core's own pre-@wordpress/scripts blocks.
 */
( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;
	var __ = i18n.__;

	var ALLOWED_BLOCKS = [
		'core/heading',
		'core/paragraph',
		'core/group',
		'core/columns',
		'core/column',
		'core/list',
		'core/list-item',
		'core/buttons',
		'core/button',
		'axell/form-fieldset',
		'axell/form-submission-notification',
	];

	// A fresh insert defaults to a plain group wrapping one label+control
	// pair — not a fieldset (that's an explicit, separate choice; see the
	// "Fieldset"/"Legend" core/group variations registered below).
	var TEMPLATE = [
		[ 'core/group', { layout: { type: 'constrained' } }, [
			[ 'axell/form-label', {} ],
			[ 'axell/form-control', {} ],
		] ],
	];

	// Same icon as the reference block (Gutenberg 23.9.1's core/form,
	// packages/block-library/src/form/icons.js — copied verbatim, since
	// block.json's `icon` field only accepts a Dashicon slug string, not
	// custom SVG markup; a real icon element has to be registered here in
	// the JS settings instead. See this plugin's CLAUDE.md.
	var ICON = el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', role: 'img', 'aria-hidden': 'true', focusable: 'false' },
		el( 'path', {
			d: 'M18 16H6c-1.1 0-2 .9-2 2s.9 2 2 2h12c1.1 0 2-.9 2-2s-.9-2-2-2Zm0 2.5H6c-.3 0-.5-.2-.5-.5s.2-.5.5-.5h12c.3 0 .5.2.5.5s-.2.5-.5.5ZM13 13H4v1.5h9V13Zm-7-2h12c1.1 0 2-.9 2-2s-.9-2-2-2H6c-1.1 0-2 .9-2 2s.9 2 2 2Zm0-2.5h12c.3 0 .5.2.5.5s-.2.5-.5.5H6c-.3 0-.5-.2-.5-.5s.2-.5.5-.5ZM13 4H4v1.5h9V4Z',
		} )
	);

	function formProps( attributes, extra ) {
		return Object.assign(
			{},
			attributes.submitsToRest ? { 'data-aa-club-form': '', noValidate: true } : {},
			extra || {}
		);
	}

	blocks.registerBlockType( 'axell/form', {
		icon: ICON,
		edit: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps();
			var innerBlocksProps = useInnerBlocksProps(
				formProps( attributes, blockProps ),
				{
					allowedBlocks: ALLOWED_BLOCKS,
					template: TEMPLATE,
					templateLock: false,
				}
			);
			return el( 'form', innerBlocksProps );
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save();
			var innerBlocksProps = useInnerBlocksProps.save( formProps( attributes, blockProps ) );
			return el( 'form', innerBlocksProps );
		},
	} );

	// core/group variations — a plain wrapper with tagName:"fieldset"/
	// "legend" (a real HTML mechanism `core/group` already has; the
	// Inspector's own "HTML element" picker just doesn't expose these two
	// values from its fixed list — the underlying attribute has no such
	// restriction). Registered here (piggybacking on axell/form's own
	// editorScript, which is always loaded in the block editor) rather
	// than needing a new enqueue_block_editor_assets hook — see this
	// plugin's CLAUDE.md for why axell/form-fieldset itself stays a real
	// custom block instead of also becoming a variation (it needs a
	// stateful "Add legend"/"Remove legend" toolbar toggle, which
	// variations can't provide).
	blocks.registerBlockVariation( 'core/group', {
		name: 'form-fieldset-group',
		title: __( 'Fieldset (plain)', 'axellcore-atelier' ),
		description: __( 'A plain grouping wrapper rendered as a real <fieldset> — no legend. For a fieldset with a toggleable legend, use the Form Fieldset block instead.', 'axellcore-atelier' ),
		icon: 'editor-table',
		attributes: { tagName: 'fieldset' },
		scope: [ 'inserter', 'transform' ],
		isActive: function ( blockAttributes ) {
			return blockAttributes.tagName === 'fieldset';
		},
	} );
	blocks.registerBlockVariation( 'core/group', {
		name: 'form-legend-group',
		title: __( 'Legend (plain)', 'axellcore-atelier' ),
		description: __( 'A plain wrapper rendered as a real <legend> — for hand-composing a fieldset from plain groups instead of the Form Fieldset block.', 'axellcore-atelier' ),
		icon: 'editor-textcolor',
		attributes: { tagName: 'legend' },
		scope: [ 'inserter', 'transform' ],
		isActive: function ( blockAttributes ) {
			return blockAttributes.tagName === 'legend';
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
