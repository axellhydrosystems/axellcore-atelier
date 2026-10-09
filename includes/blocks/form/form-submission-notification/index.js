/**
 * axell/form-submission-notification — an editable success/error message
 * inside axell/form. Static (no PHP render), no build step. Replaces a
 * blocking window.alert() (see assets/js/frontend.js's submit handler) —
 * that gap and the heritage of this block (ported from Gutenberg's removed
 * experimental core/form-submission-notification, adapted for our fetch()-
 * based, no-page-reload submission) are documented in this plugin's own
 * CLAUDE.md.
 *
 * Frontend visibility: `.aa-notice` starts hidden (assets/css/sections.css)
 * and frontend.js adds `.is-active` to the matching
 * `[data-aa-notice-type="success"|"error"]` element once its form's fetch()
 * resolves. In the editor, this hiding rule doesn't apply (no plugin CSS
 * loads inside the editor iframe yet — a separately tracked gap, see
 * CLAUDE.md), so both notifications render fully visible/editable here
 * without extra effort. If that editor-CSS gap is ever closed, re-check that
 * this block stays visible in the editor regardless.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var __ = i18n.__;

	var ALLOWED_BLOCKS = [ 'core/paragraph', 'core/heading' ];
	var TEMPLATE = [ [ 'core/paragraph', {} ] ];

	function classesFor( attributes ) {
		return [ 'aa-notice', 'aa-notice-' + attributes.type ].join( ' ' );
	}

	function noticeProps( attributes, extra ) {
		return Object.assign(
			{ 'data-aa-notice-type': attributes.type },
			extra || {}
		);
	}

	blocks.registerBlockType( 'axell/form-submission-notification', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: classesFor( attributes ) } );
			var innerBlocksProps = useInnerBlocksProps(
				noticeProps( attributes, blockProps ),
				{
					allowedBlocks: ALLOWED_BLOCKS,
					template: TEMPLATE,
					templateLock: false,
				}
			);

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Notification', 'axellcore-atelier' ) },
						el( SelectControl, {
							label: __( 'Type', 'axellcore-atelier' ),
							value: attributes.type,
							options: [
								{ label: __( 'Success', 'axellcore-atelier' ), value: 'success' },
								{ label: __( 'Error', 'axellcore-atelier' ), value: 'error' },
							],
							onChange: function ( v ) {
								setAttributes( { type: v } );
							},
						} )
					)
				),
				el( 'div', innerBlocksProps )
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save( { className: classesFor( attributes ) } );
			var innerBlocksProps = useInnerBlocksProps.save( noticeProps( attributes, blockProps ) );
			return el( 'div', innerBlocksProps );
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
