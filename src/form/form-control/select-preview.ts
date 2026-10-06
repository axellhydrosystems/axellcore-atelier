import { useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

/**
 * Editor props for a block whose root element is a <select>: a mouse down
 * selects the block (to change its settings) and does not open the list.
 *
 * @param clientId Block client id.
 * @param isSelect The block renders a select.
 */
export function useSelectPreview( clientId: string, isSelect: boolean ): Record< string, unknown > {
	const { selectBlock } = useDispatch( blockEditorStore );
	return isSelect
		? {
				onMouseDown: ( event: MouseEvent ) => {
					event.preventDefault();
					selectBlock( clientId );
				},
		  }
		: {};
}
