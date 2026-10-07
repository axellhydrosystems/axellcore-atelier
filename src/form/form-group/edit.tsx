import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { FIELD_BLOCKS } from '../field-blocks';

const ALLOWED_BLOCKS = FIELD_BLOCKS;

const TEMPLATE: Array< [ string, Record< string, unknown > ] > = [
	[ 'axell/form-label', {} ],
	[ 'axell/form-control', {} ],
];

/**
 * axell/form-group — one form field: a label, a form control (or select) and
 * an optional axell/form-text. A plain block with no layout options of its
 * own: to place fields side by side, put them in a core/group grid inside the
 * fieldset and set each field's span (the core grid child controls).
 */
export default function Edit() {
	const innerBlocksProps = useInnerBlocksProps( useBlockProps(), {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE,
		templateInsertUpdatesSelection: false,
	} );
	return <div { ...innerBlocksProps } />;
}
