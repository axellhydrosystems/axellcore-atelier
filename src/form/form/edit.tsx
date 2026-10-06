import type { BlockEditProps } from '@wordpress/blocks';
import { FormEdit } from './form-edit';
import type { FormAttributes } from './types';

/**
 * axell/form — a generic form: inner blocks plus optional submission actions
 * (store as a post of a chosen type, send an email).
 * @param props Block edit props.
 */
export default function Edit( props: BlockEditProps< FormAttributes > ) {
	return FormEdit( props );
}
