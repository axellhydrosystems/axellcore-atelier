import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataViews } from '@wordpress/dataviews';
import type { Action, Field, View } from '@wordpress/dataviews';
import { Notice } from '@wordpress/components';
import { fetchResellers, trashReseller } from './api';
import type { ListQuery, ListResult } from './api';
import type { Reseller, ResellersConfig } from './types';

const DEFAULT_VIEW: View = {
	type: 'table',
	page: 1,
	perPage: 20,
	search: '',
	sort: { field: 'title', direction: 'asc' },
	filters: [],
	fields: [ 'city', 'state', 'phone_1', 'status', 'date' ],
	titleField: 'title',
	layout: {},
};

function filterValue( view: View, field: string ): string {
	const filter = ( view.filters ?? [] ).find( ( f ) => f.field === field );
	return filter && typeof filter.value === 'string' ? filter.value : '';
}

function toQuery( view: View ): ListQuery {
	const sort = view.sort ?? { field: 'title', direction: 'asc' };
	return {
		page: view.page ?? 1,
		perPage: view.perPage ?? 20,
		search: view.search ?? '',
		state: filterValue( view, 'state' ),
		city: filterValue( view, 'city' ),
		status: filterValue( view, 'status' ),
		orderby: sort.field === 'date' ? 'date' : 'title',
		order: sort.direction,
	};
}

export default function ResellersList( {
	config,
}: {
	config: ResellersConfig;
} ) {
	const { editUrl, statuses, stateTerms, cityTerms } = config;
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );
	const [ reload, setReload ] = useState( 0 );
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
		fetchResellers( toQuery( view ) )
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
								'Could not load the resellers.',
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
	}, [ view, reload ] );

	const onChangeView = useCallback(
		( next: View ) => {
			// Anything but paging returns to the first page.
			const pageChanged = next.page !== view.page;
			setView( { ...next, page: pageChanged ? next.page : 1 } );
		},
		[ view.page ]
	);

	// State and city filter by term slug; the row shows the term name.
	const fields: Field< Reseller >[] = useMemo(
		() => [
			{
				id: 'title',
				label: __( 'Name', 'axellcore-atelierclub' ),
				type: 'text',
				enableHiding: false,
			},
			{
				id: 'city',
				label: __( 'City', 'axellcore-atelierclub' ),
				type: 'text',
				enableSorting: false,
				elements: cityTerms,
				getValue: ( { item } ) =>
					cityTerms.find( ( term ) => term.label === item.city )
						?.value ?? item.city,
				render: ( { item } ) => <>{ item.city }</>,
				filterBy: { operators: [ 'is' ] },
			},
			{
				id: 'state',
				label: __( 'State', 'axellcore-atelierclub' ),
				type: 'text',
				enableSorting: false,
				elements: stateTerms,
				getValue: ( { item } ) =>
					stateTerms.find( ( term ) => term.label === item.state )
						?.value ?? item.state,
				render: ( { item } ) => <>{ item.state }</>,
				filterBy: { operators: [ 'is' ] },
			},
			{
				id: 'country',
				label: __( 'Country', 'axellcore-atelierclub' ),
				type: 'text',
				enableSorting: false,
			},
			{
				id: 'phone_1',
				label: __( 'Phone', 'axellcore-atelierclub' ),
				type: 'telephone',
				enableSorting: false,
			},
			{
				id: 'email',
				label: __( 'Email', 'axellcore-atelierclub' ),
				type: 'email',
				enableSorting: false,
			},
			{
				id: 'website',
				label: __( 'Website', 'axellcore-atelierclub' ),
				type: 'url',
				enableSorting: false,
			},
			{
				id: 'status',
				label: __( 'Status', 'axellcore-atelierclub' ),
				type: 'text',
				enableSorting: false,
				elements: statuses,
				filterBy: { operators: [ 'is' ] },
			},
			{
				id: 'date',
				label: __( 'Date', 'axellcore-atelierclub' ),
				type: 'datetime',
			},
		],
		[ statuses, stateTerms, cityTerms ]
	);

	const actions: Action< Reseller >[] = useMemo(
		() => [
			{
				id: 'edit',
				label: __( 'Edit', 'axellcore-atelierclub' ),
				isPrimary: true,
				callback: ( items ) => {
					if ( items[ 0 ] ) {
						window.location.href = `${ editUrl }${ items[ 0 ].id }`;
					}
				},
			},
			{
				id: 'trash',
				label: __( 'Move to trash', 'axellcore-atelierclub' ),
				isDestructive: true,
				supportsBulk: true,
				callback: ( items ) => {
					Promise.all(
						items.map( ( item ) => trashReseller( item.id ) )
					)
						.catch( ( err: { message?: string } ) =>
							setError( err?.message ?? null )
						)
						.finally( () => setReload( ( n ) => n + 1 ) );
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
			<DataViews< Reseller >
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
				isItemClickable={ () => true }
				empty={ __(
					'No resellers found.',
					'axellcore-atelierclub'
				) }
			/>
		</>
	);
}
