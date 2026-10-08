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

/**
 * CPF (11 digits) or CNPJ (14 characters, letters allowed in the first 12),
 * masked for display.
 *
 * @param value Stored CPF/CNPJ.
 */
export function formatDocument( value: string ): string {
	if ( /^\d{11}$/.test( value ) ) {
		return value.replace( /(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4' );
	}
	if ( /^[0-9A-Z]{12}\d{2}$/.test( value ) ) {
		return value.replace(
			/(.{2})(.{3})(.{3})(.{4})(\d{2})/,
			'$1.$2.$3/$4-$5'
		);
	}
	return value;
}

/**
 * A stored phone (+5511987654321) as (11) 98765-4321.
 *
 * @param value Stored phone.
 */
export function formatPhone( value: string ): string {
	let digits = value.replace( /\D/g, '' );
	if ( digits.length > 11 && digits.startsWith( '55' ) ) {
		digits = digits.slice( 2 );
	}
	const parts = digits.match( /^(\d{2})(\d{4,5})(\d{4})$/ );
	return parts ? `(${ parts[ 1 ] }) ${ parts[ 2 ] }-${ parts[ 3 ] }` : value;
}

/**
 * A stored CEP (01001000) as 01001-000.
 *
 * @param value Stored CEP.
 */
export function formatPostal( value: string ): string {
	return /^\d{8}$/.test( value )
		? `${ value.slice( 0, 5 ) }-${ value.slice( 5 ) }`
		: value;
}

export interface SelectOption {
	value: string;
	label: string;
}

export interface MembersConfig {
	listUrl: string;
	editUrl: string;
	states: SelectOption[];
	/** States that have members (the list's filter). */
	usedStates: SelectOption[];
	primaryFocus: SelectOption[];
	statuses: SelectOption[];
	memberId: number;
}

declare global {
	interface Window {
		aaMembers?: MembersConfig;
	}
}
