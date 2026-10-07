import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataViews } from '@wordpress/dataviews';
import type { Action, Field, View } from '@wordpress/dataviews';
import { Notice } from '@wordpress/components';
import { fetchMembers } from './api';
import type { ListQuery, ListResult } from './api';
import { formatDocument } from './types';
import type { MemberSummary } from './types';

const DEFAULT_VIEW: View = {
	type: 'table',
	page: 1,
	perPage: 20,
	search: '',
	sort: { field: 'data', direction: 'desc' },
	filters: [],
	fields: [
		'fullname',
		'company',
		'city',
		'state',
		'primary_focus',
		'data',
	],
	layout: {},
};

const SORT_FIELD_TO_ORDERBY: Record< string, ListQuery[ 'orderby' ] > = {
	fullname: 'title',
	state: 'state',
	data: 'date',
};

function filterValue( view: View, field: string ): string {
	const filter = ( view.filters ?? [] ).find( ( f ) => f.field === field );
	return filter && typeof filter.value === 'string' ? filter.value : '';
}

function toQuery( view: View ): ListQuery {
	const sort = view.sort ?? { field: 'data', direction: 'desc' };
	return {
		page: view.page ?? 1,
		perPage: view.perPage ?? 20,
		search: view.search ?? '',
		state: filterValue( view, 'state' ),
		primaryFocus: filterValue( view, 'primary_focus' ),
		orderby: SORT_FIELD_TO_ORDERBY[ sort.field ] ?? 'date',
		order: sort.direction,
	};
}

export default function MembersList( {
	states,
	primaryFocus,
	statuses,
	editUrl,
}: {
	states: Array< { value: string; label: string } >;
	primaryFocus: Array< { value: string; label: string } >;
	statuses: Array< { value: string; label: string } >;
	editUrl: string;
} ) {
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );
	const [ result, setResult ] = useState< ListResult >( {
		items: [],
		total: 0,
		totalPages: 0,
	} );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		let cancelled = false;
		setIsLoading( true );
		fetchMembers( toQuery( view ) )
			.then( ( data ) => {
				if ( ! cancelled ) {
					setResult( data );
					setError( null );
				}
			} )
			.catch( ( err: { message?: string } ) => {
				if ( ! cancelled ) {
					setError(
						err?.message ??
							__(
								'Could not load members.',
								'axellcore-atelierclub'
							)
					);
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setIsLoading( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ view ] );

	const onChangeView = useCallback(
		( next: View ) => {
			// Any change other than paging itself (search, filter, sort, page size)
			// returns to the first page, so the user never lands on an empty page.
			const pageChanged = next.page !== view.page;
			setView( { ...next, page: pageChanged ? next.page : 1 } );
		},
		[ view.page ]
	);

	const fields: Field< MemberSummary >[] = useMemo(
		() => [
			{
				id: 'fullname',
				label: __( 'Name', 'axellcore-atelierclub' ),
				type: 'text',
				enableHiding: false,
			},
			{
				id: 'company',
				label: __( 'Office', 'axellcore-atelierclub' ),
				type: 'text',
				enableSorting: false,
			},
			{
				id: 'city',
				label: __( 'City', 'axellcore-atelierclub' ),
				type: 'text',
				enableSorting: false,
			},
			{
				id: 'state',
				label: __( 'State code', 'axellcore-atelierclub' ),
				type: 'text',
				// UF is shown by its code (PR), not the state name.
				elements: states.map( ( state ) => ( {
					value: state.value,
					label: state.value,
				} ) ),
				filterBy: { operators: [ 'is' ] },
			},
			{
				id: 'primary_focus',
				label: __( 'Practice', 'axellcore-atelierclub' ),
				type: 'text',
				enableSorting: false,
				elements: primaryFocus,
				filterBy: { operators: [ 'is' ] },
			},
			{
				id: 'email',
				label: __( 'Email', 'axellcore-atelierclub' ),
				type: 'email',
				enableSorting: false,
			},
			{
				id: 'phone',
				label: __( 'Phone', 'axellcore-atelierclub' ),
				type: 'telephone',
				enableSorting: false,
			},
			{
				id: 'br_revenue_id',
				label: __( 'CPF / CNPJ', 'axellcore-atelierclub' ),
				type: 'text',
				getValue: ( { item } ) => formatDocument( item.br_revenue_id ),
				enableSorting: false,
			},
			{
				id: 'data',
				label: __( 'Submitted on', 'axellcore-atelierclub' ),
				type: 'datetime',
				enableHiding: false,
			},
			{
				id: 'status',
				label: __( 'Status', 'axellcore-atelierclub' ),
				type: 'text',
				enableSorting: false,
				elements: statuses,
			},
		],
		[ states, primaryFocus, statuses ]
	);

	const actions: Action< MemberSummary >[] = useMemo(
		() => [
			{
				id: 'edit',
				label: __( 'View', 'axellcore-atelierclub' ),
				isPrimary: true,
				callback: ( items ) => {
					if ( items[ 0 ] ) {
						window.location.href = `${ editUrl }${ items[ 0 ].id }`;
					}
				},
			},
		],
		[ editUrl ]
	);

	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<DataViews< MemberSummary >
				view={ view }
				onChangeView={ onChangeView }
				fields={ fields }
				actions={ actions }
				data={ result.items }
				getItemId={ ( item ) => String( item.id ) }
				isLoading={ isLoading }
				paginationInfo={ {
					totalItems: result.total,
					totalPages: result.totalPages,
				} }
				defaultLayouts={ { table: {} } }
				config={ { perPageSizes: [ 10, 20, 50, 100 ] } }
				onClickItem={ ( item ) => {
					window.location.href = `${ editUrl }${ item.id }`;
				} }
				empty={ __(
					'No registrations found.',
					'axellcore-atelierclub'
				) }
			/>
		</>
	);
}
