export interface FormControlOption {
	label: string;
	value: string;
}

export type FormControlType =
	| 'text'
	| 'email'
	| 'url'
	| 'number'
	| 'tel'
	| 'textarea'
	| 'select'
	| 'hidden'
	| 'autocomplete';

export interface FormControlAttributes {
	type: FormControlType;
	id: string;
	name: string;
	required: boolean;
	placeholder: string;
	value: string;
	checked: boolean;
	options: FormControlOption[];
	mask: string;
	maskSourceName: string;
	citiesSourceName: string;
	autofill: string;
	sourcePostType: string;
	labelTemplate: string;
	allowNotFound: boolean;
	[ key: string ]: unknown;
}
