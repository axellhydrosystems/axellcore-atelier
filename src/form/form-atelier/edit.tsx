import type { BlockEditProps } from '@wordpress/blocks';
import { FormEdit } from '../form/form-edit';
import type { FormAttributes } from '../form/types';
import { ATELIER_TEMPLATE } from './template';

/**
 * axell/form-atelier — the atelier application form: stores into members
 * (fixed) and starts with the full template; the email action stays optional.
 * @param props Block edit props.
 */
export default function Edit( props: BlockEditProps< FormAttributes > ) {
	return FormEdit( props, { lockedStore: true, initialMarkup: ATELIER_TEMPLATE } );
}
