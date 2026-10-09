import apiFetch from '@wordpress/api-fetch';
import type { Reseller } from './types';

const BASE = '/axellcore-atelier/v1/admin/resellers';

export interface ListQuery {
	page: number;
	perPage: number;
	search: string;
	state: string;
	city: string;
	status: string;
	orderby: 'date' | 'title';
	order: 'asc' | 'desc';
}

export interface ListResult {
	items: Reseller[];
	total: number;
	totalPages: number;
}

export async function fetchResellers(
	query: ListQuery
): Promise< ListResult > {
	const params = new URLSearchParams( {
		page: String( query.page ),
		per_page: String( query.perPage ),
		search: query.search,
		state: query.state,
		city: query.city,
		status: query.status,
		orderby: query.orderby,
		order: query.order,
	} );
	const response = ( await apiFetch( {
		path: `${ BASE }?${ params.toString() }`,
		parse: false,
	} ) ) as Response;

	return {
		items: ( await response.json() ) as Reseller[],
		total: Number( response.headers.get( 'X-WP-Total' ) ?? 0 ),
		totalPages: Number( response.headers.get( 'X-WP-TotalPages' ) ?? 0 ),
	};
}

export function fetchReseller( id: number ): Promise< Reseller > {
	return apiFetch< Reseller >( { path: `${ BASE }/${ id }` } );
}

/**
 * Create (no id) or update a revenda.
 * @param id
 * @param data
 */
export function saveReseller(
	id: number,
	data: Record< string, string >
): Promise< Reseller > {
	return apiFetch< Reseller >( {
		path: id ? `${ BASE }/${ id }` : BASE,
		method: 'POST',
		data,
	} );
}

export function trashReseller( id: number ): Promise< unknown > {
	return apiFetch( { path: `${ BASE }/${ id }`, method: 'DELETE' } );
}
