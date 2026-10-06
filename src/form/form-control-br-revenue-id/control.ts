import type { FormControlAttributes } from '../form-control/types';

export interface BrDocumentAttributes {
	id: string;
	name: string;
	placeholder: string;
	required: boolean;
	[ key: string ]: unknown;
}

/**
 * The text control of this block: a text field with the cpf-cnpj mask, built
 * from the form-control attributes so the markup is the same as the other controls.
 *
 * @param attributes Block attributes.
 */
export function controlAttributes( attributes: BrDocumentAttributes, mask = 'cpf-cnpj' ): FormControlAttributes {
	return {
		type: 'text',
		id: attributes.id,
		name: attributes.name || attributes.id,
		required: !! attributes.required,
		placeholder: attributes.placeholder,
		mask,
		maskSourceName: '',
		citiesSourceName: '',
		sourcePostType: '',
		labelTemplate: '',
		allowNotFound: false,
		value: '',
		checked: false,
		options: [],
		autofill: '',
	} as unknown as FormControlAttributes;
}
