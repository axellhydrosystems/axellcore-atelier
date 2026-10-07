export interface MemberSummary {
	id: number;
	fullname: string;
	company: string;
	email: string;
	phone: string;
	primary_focus: string;
	state: string;
	city: string;
	br_revenue_id: string;
	data: string;
	status: string;
}

export interface MemberReseller {
	field: string;
	title: string;
	id: number;
	status: string;
	pending: boolean;
	url: string;
}

export interface MemberDetail {
	id: number;
	fullname: string;
	email: string;
	url: string;
	state: string;
	city: string;
	login: string;
	data: string;
	status: string;
	company: string;
	phone: string;
	professional_registration: string;
	primary_focus: string;
	profile_type: string;
	br_revenue_id: string;
	address_street: string;
	address_number: string;
	address_2: string;
	neighborhood: string;
	landmark: string;
	postal: string;
	reseller1: string;
	reseller2: string;
	reseller3: string;
	reseller4: string;
	reseller5: string;
	resellers?: MemberReseller[];
}

/** CPF (11 digits) or CNPJ (14 digits), masked for display. */
export function formatDocument( digits: string ): string {
	if ( /^\d{11}$/.test( digits ) ) {
		return digits.replace( /(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4' );
	}
	if ( /^\d{14}$/.test( digits ) ) {
		return digits.replace( /(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5' );
	}
	return digits;
}

export interface SelectOption {
	value: string;
	label: string;
}

export interface MembersConfig {
	listUrl: string;
	editUrl: string;
	states: SelectOption[];
	primaryFocus: SelectOption[];
	statuses: SelectOption[];
	memberId: number;
}

declare global {
	interface Window {
		aaMembers?: MembersConfig;
	}
}
