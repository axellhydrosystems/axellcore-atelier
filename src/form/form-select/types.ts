export interface FormSelectOption {
	label: string;
	value: string;
}

export interface FormSelectAttributes {
	id: string;
	name: string;
	required: boolean;
	placeholder: string;
	options: FormSelectOption[];
	[ key: string ]: unknown;
}
