export interface AddressAttributes {
	id: string;
	name: string;
	placeholder: string;
	required: boolean;
	countryField?: string;
	countrySource?: string;
	country?: string;
	stateField?: string;
	searchable?: boolean;
	fixed?: string;
	hiddenField?: boolean;
	/** Phone: line type (Brazil only): both, mobile or landline. */
	lineType?: string;
	[ key: string ]: unknown;
}
