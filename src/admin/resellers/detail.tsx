import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataForm } from '@wordpress/dataviews';
import type { Field, Form, FormField } from '@wordpress/dataviews';
import { Button, Notice } from '@wordpress/components';
import { fetchReseller, saveReseller, trashReseller } from './api';
import type { Reseller, ResellersConfig } from './types';

type Draft = Record< string, string >;

const EMPTY: Draft = {
	title: '',
	status: 'publish',
	country: 'Brasil',
	state: '',
	city: '',
	address: '',
	phone_1: '',
	phone_2: '',
	website: '',
	email: '',
};

function toDraft( reseller: Reseller ): Draft {
	const draft: Draft = {};
	for ( const [ key, value ] of Object.entries( reseller ) ) {
		draft[ key ] =
			value === null || value === undefined ? '' : String( value );
	}
	return draft;
}

const asOptions = ( values: string[] ) =>
	values.map( ( value ) => ( { value, label: value } ) );

export default function ResellerDetailView( {
	config,
}: {
	config: ResellersConfig;
} ) {
	const { listUrl, editUrl, statuses, countries, states } = config;
	const [ id, setId ] = useState( config.isNew ? 0 : config.resellerId );
	const [ saved, setSaved ] = useState< Draft | null >(
		config.isNew ? EMPTY : null
	);
	const [ draft, setDraft ] = useState< Draft | null >(
		config.isNew ? EMPTY : null
	);
	const [ isBusy, setIsBusy ] = useState( false );
	const [ notice, setNotice ] = useState< {
		status: 'success' | 'error';
		message: string;
	} | null >( null );

	useEffect( () => {
		if ( ! id || saved ) {
			return;
		}
		fetchReseller( id )
			.then( ( reseller ) => {
				setSaved( toDraft( reseller ) );
				setDraft( toDraft( reseller ) );
			} )
			.catch( ( err: { message?: string } ) =>
				setNotice( {
					status: 'error',
					message:
						err?.message ??
						__(
							'Could not load the reseller.',
							'axellcore-atelierclub'
						),
				} )
			);
	}, [ id, saved ] );

	const isBrazil = ( draft?.country ?? '' ).toLowerCase() === 'brasil';

	const fields: Field< Draft >[] = useMemo(
		() => [
			{
				id: 'title',
				label: __( 'Name', 'axellcore-atelierclub' ),
				type: 'text',
				isValid: { required: true },
			},
			{
				id: 'status',
				label: __( 'Status', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: statuses,
			},
			{
				id: 'address',
				label: __( 'Address', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'phone_1',
				label: __( 'Phone 1', 'axellcore-atelierclub' ),
				type: 'telephone',
			},
			{
				id: 'phone_2',
				label: __( 'Phone 2', 'axellcore-atelierclub' ),
				type: 'telephone',
			},
			{
				id: 'website',
				label: __( 'Website', 'axellcore-atelierclub' ),
				type: 'url',
			},
			{
				id: 'email',
				label: __( 'Email', 'axellcore-atelierclub' ),
				type: 'email',
			},
			{
				id: 'country',
				label: __( 'Country', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: asOptions( countries ),
			},
			// Brazil: the state from the list (stored by its full name).
			isBrazil
				? {
						id: 'state',
						label: __( 'State', 'axellcore-atelierclub' ),
						type: 'text',
						Edit: 'select',
						elements: asOptions( states ),
					}
				: {
						id: 'state',
						label: __( 'State', 'axellcore-atelierclub' ),
						type: 'text',
					},
			{
				id: 'city',
				label: __( 'City', 'axellcore-atelierclub' ),
				type: 'text',
			},
		],
		[ statuses, countries, states, isBrazil ]
	);

	const row = ( key: string, children: string[] ) => ( {
		id: `row-${ key }`,
		label: '',
		children,
		layout: { type: 'row' as const },
	} );
	const card = (
		key: string,
		label: string,
		children: FormField[ 'children' ]
	): FormField => ( {
		id: key,
		label,
		children,
		layout: { type: 'card' as const, withHeader: true, isOpened: true },
	} );

	const form: Form = {
		fields: [
			card( 'revenda', __( 'Reseller', 'axellcore-atelierclub' ), [
				row( 'revenda-1', [ 'title', 'status' ] ),
			] ),
			card( 'contato', __( 'Contact', 'axellcore-atelierclub' ), [
				row( 'contato-1', [ 'phone_1', 'phone_2' ] ),
				row( 'contato-2', [ 'email', 'website' ] ),
			] ),
			card( 'local', __( 'Location', 'axellcore-atelierclub' ), [
				'address',
				row( 'local-1', [ 'country', 'state', 'city' ] ),
			] ),
		],
	};

	if ( ! draft || ! saved ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.message }
			</Notice>
		) : null;
	}

	const changes: Draft = {};
	for ( const [ key, value ] of Object.entries( draft ) ) {
		if ( id === 0 || value !== saved[ key ] ) {
			changes[ key ] = value;
		}
	}
	const isDirty = Object.keys( changes ).length > 0;

	function onChange( edits: Partial< Draft > ) {
		setDraft( ( prev ) => {
			if ( ! prev ) {
				return prev;
			}
			const next: Draft = { ...prev };
			for ( const [ key, value ] of Object.entries( edits ) ) {
				next[ key ] = value ?? '';
			}
			return next;
		} );
	}

	function onSave() {
		setIsBusy( true );
		saveReseller( id, changes )
			.then( ( reseller ) => {
				if ( ! id ) {
					// The new revenda now has its own URL.
					window.history.replaceState(
						null,
						'',
						`${ editUrl }${ reseller.id }`
					);
					setId( reseller.id );
				}
				setSaved( toDraft( reseller ) );
				setDraft( toDraft( reseller ) );
				setNotice( {
					status: 'success',
					message: id
						? __( 'Reseller updated.', 'axellcore-atelierclub' )
						: __( 'Reseller created.', 'axellcore-atelierclub' ),
				} );
			} )
			.catch( ( err: { message?: string } ) =>
				setNotice( {
					status: 'error',
					message:
						err?.message ||
						__(
							'Could not save.',
							'axellcore-atelierclub'
						),
				} )
			)
			.finally( () => setIsBusy( false ) );
	}

	function onTrash() {
		setIsBusy( true );
		trashReseller( id )
			.then( () => {
				window.location.href = listUrl;
			} )
			.catch( ( err: { message?: string } ) => {
				setIsBusy( false );
				setNotice( {
					status: 'error',
					message: err?.message ?? '',
				} );
			} );
	}

	return (
		<>
			<p>
				<a href={ listUrl }>
					{ __( '← All resellers', 'axellcore-atelierclub' ) }
				</a>
			</p>
			{ notice && (
				<Notice
					status={ notice.status }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<DataForm< Draft >
				data={ draft }
				fields={ fields }
				form={ form }
				onChange={ onChange }
			/>
			<div className="aa-resellers-actions">
				<Button
					variant="primary"
					onClick={ onSave }
					isBusy={ isBusy }
					disabled={
						isBusy || ! isDirty || '' === draft.title.trim()
					}
					__next40pxDefaultSize
				>
					{ id
						? __( 'Save', 'axellcore-atelierclub' )
						: __( 'Create reseller', 'axellcore-atelierclub' ) }
				</Button>
				{ id > 0 && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ onTrash }
						disabled={ isBusy }
						__next40pxDefaultSize
					>
						{ __(
							'Move to trash',
							'axellcore-atelierclub'
						) }
					</Button>
				) }
			</div>
		</>
	);
}
