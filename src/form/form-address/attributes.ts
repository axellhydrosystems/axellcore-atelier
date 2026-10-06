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
	[ key: string ]: unknown;
}
