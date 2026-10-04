import { useInnerBlocksProps, useBlockProps } from '@wordpress/block-editor';

/**
 * The canvas keeps the header in normal flow: `position: fixed` would pin it
 * over the editor's own toolbar and the rest of the canvas. The fixed
 * behaviour only applies on the front end (see style.scss).
 */
export default function Edit() {
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps );

	return <div { ...innerBlocksProps } />;
}
