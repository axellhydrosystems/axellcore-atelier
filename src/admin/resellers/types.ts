export interface Reseller {
	id: number;
	title: string;
	status: string;
	date: string;
	country: string;
	state: string;
	city: string;
	address: string;
	phone_1: string;
	phone_2: string;
	website: string;
	email: string;
}

export interface SelectOption {
	value: string;
	label: string;
}

export interface ResellersConfig {
	listUrl: string;
	editUrl: string;
	resellerId: number;
	isNew: boolean;
	statuses: SelectOption[];
	stateTerms: SelectOption[];
	cityTerms: SelectOption[];
	countries: string[];
	states: string[];
}

declare global {
	interface Window {
		aaResellers?: ResellersConfig;
	}
}
