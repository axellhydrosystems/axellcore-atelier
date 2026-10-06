/**
 * axellcore/chapters — wraps the page's numbered chapters (axellcore/chapter)
 * and establishes the CSS counter scope (.aa-chapters { counter-reset:
 * aa-chapter }) each child increments. Purely structural: no attributes,
 * no PHP render — same static-block pattern as axellcore/form. No build
 * step - plain browser JS against the wp.* globals, same pattern as core's
 * own pre-@wordpress/scripts blocks.
 */
( function ( blocks, element, blockEditor ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;

	var ALLOWED_BLOCKS = [ 'axellcore/chapter' ];
	var TEMPLATE = [ [ 'axellcore/chapter', {} ] ];

	blocks.registerBlockType( 'axellcore/chapters', {
		edit: function () {
			var blockProps = useBlockProps( { className: 'aa-chapters' } );
			var innerBlocksProps = useInnerBlocksProps( blockProps, {
				allowedBlocks: ALLOWED_BLOCKS,
				template: TEMPLATE,
				templateLock: false,
				orientation: 'vertical',
			} );
			return el( 'div', innerBlocksProps );
		},
		save: function () {
			var blockProps = useBlockProps.save( { className: 'aa-chapters' } );
			var innerBlocksProps = useInnerBlocksProps.save( blockProps );
			return el( 'div', innerBlocksProps );
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor );
