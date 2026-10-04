import apiFetch from '@wordpress/api-fetch';
import type { MemberDetail, MemberSummary, SelectOption } from './types';

const BASE = '/axellcore-atelierclub/v1';

export interface ListQuery {
	page: number;
	perPage: number;
	search: string;
	uf: string;
	atuacao: string;
	orderby: 'date' | 'title' | 'uf';
	order: 'asc' | 'desc';
}

export interface ListResult {
	items: MemberSummary[];
	total: number;
	totalPages: number;
}

export async function fetchMembers( query: ListQuery ): Promise< ListResult > {
	const params = new URLSearchParams( {
		page: String( query.page ),
		per_page: String( query.perPage ),
		search: query.search,
		uf: query.uf,
		atuacao: query.atuacao,
		orderby: query.orderby,
		order: query.order,
	} );
	const response = ( await apiFetch( {
		path: `${ BASE }/admin/members?${ params.toString() }`,
		parse: false,
	} ) ) as Response;

	return {
		items: ( await response.json() ) as MemberSummary[],
		total: Number( response.headers.get( 'X-WP-Total' ) ?? 0 ),
		totalPages: Number( response.headers.get( 'X-WP-TotalPages' ) ?? 0 ),
	};
}

export function fetchMember( id: number ): Promise< MemberDetail > {
	return apiFetch< MemberDetail >( {
		path: `${ BASE }/admin/members/${ id }`,
	} );
}

export function saveMember(
	id: number,
	data: Record< string, string >
): Promise< MemberDetail > {
	return apiFetch< MemberDetail >( {
		path: `${ BASE }/admin/members/${ id }`,
		method: 'POST',
		data,
	} );
}

export function fetchCities( uf: string ): Promise< SelectOption[] > {
	return apiFetch< Array< { value: number; label: string } > >( {
		path: `${ BASE }/cities?uf=${ encodeURIComponent( uf ) }`,
	} ).then( ( items ) =>
		items.map( ( item ) => ( {
			value: String( item.value ),
			label: item.label,
		} ) )
	);
}
