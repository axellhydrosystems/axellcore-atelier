/**
 * axellcore/chapter — one numbered chapter within axellcore/chapters.
 *
 * The visible "Capítulo NN" prefix is NEVER stored as text: the number is a
 * CSS counter (.aa-chapters{counter-reset:aa-chapter} /
 * .aa-chapter{counter-increment:aa-chapter} / .aa-chapter-tag::before{
 * content: attr(data-chapter) " " counter(aa-chapter, decimal-leading-zero)
 * " · "} — see assets/css/sections.css), and the word itself ("Chapter" /
 * "Capítulo") is a real, runtime-translated gettext string, never frozen
 * into saved content:
 *  - edit() sets it via wp.i18n.__() so the editor canvas matches the
 *    site's admin locale (a block with its own JS save() always renders
 *    from edit() in the canvas — Blocks::render_chapter() is never called
 *    there, only on the actual frontend request).
 *  - save() deliberately does NOT include data-chapter at all — it isn't
 *    something this block should freeze into post_content. The frontend
 *    gets it from Blocks::render_chapter() (includes/class-blocks.php),
 *    which post-processes this same static markup with PHP's __() against
 *    the site's current locale on every request.
 *
 * `label` (the part after "·", e.g. "Manifesto") is stored as a plain
 * string attribute — deliberately NOT rich-text/HTML-sourced (see
 * axellcore/form-input's block.json for why a duplicated/escaped rich-text
 * attribute once broke the PHP block-comment parser); every label here is
 * plain text with no formatting need, so RichText is used purely as an
 * inline-edit widget with allowedFormats:[] and its value stored as an
 * ordinary string.
 *
 * No build step - plain browser JS against the wp.* globals, same pattern
 * as core's own pre-@wordpress/scripts blocks.
 */
( function ( blocks, element, blockEditor, i18n ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;
	var RichText = blockEditor.RichText;
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
		'core/table',
		'core/html',
		'axellcore/form',
	];

	blocks.registerBlockType( 'axellcore/chapter', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'aa-chapter' } );
			var innerBlocksProps = useInnerBlocksProps(
				{ className: 'aa-chapter-body' },
				{ allowedBlocks: ALLOWED_BLOCKS, templateLock: false }
			);

			return el(
				'div',
				blockProps,
				el(
					'p',
					{ className: 'aa-chapter-tag', 'data-chapter': __( 'Chapter', 'axellcore-atelierclub' ) },
					el( RichText, {
						tagName: 'span',
						className: 'aa-chapter-label',
						value: attributes.label,
						onChange: function ( label ) {
							setAttributes( { label: label } );
						},
						allowedFormats: [],
						placeholder: __( 'Chapter name…', 'axellcore-atelierclub' ),
					} )
				),
				el( 'div', innerBlocksProps )
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save( { className: 'aa-chapter' } );
			var innerBlocksProps = useInnerBlocksProps.save( { className: 'aa-chapter-body' } );

			return el(
				'div',
				blockProps,
				el(
					'p',
					{ className: 'aa-chapter-tag' },
					el( RichText.Content, {
						tagName: 'span',
						className: 'aa-chapter-label',
						value: attributes.label,
					} )
				),
				el( 'div', innerBlocksProps )
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
