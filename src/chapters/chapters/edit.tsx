import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED_BLOCKS = [ 'axellcore/chapter' ];
const TEMPLATE: Array< [ string, Record< string, unknown > ] > = [
	[ 'axellcore/chapter', {} ],
];

/**
 * axellcore/chapters — wraps the page's numbered chapters (axellcore/chapter)
 * and establishes the CSS counter scope (.aa-chapters { counter-reset:
 * aa-chapter }) each child increments. Purely structural: no attributes,
 * no PHP render — same static-block pattern as axell/form.
 */
export default function Edit() {
	const blockProps = useBlockProps( { className: 'aa-chapters' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE,
		templateLock: false,
		orientation: 'vertical',
	} );
	return <div { ...innerBlocksProps } />;
}
