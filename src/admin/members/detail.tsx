import { RawHTML, useEffect, useMemo, useState } from '@wordpress/element';
import { __, _x } from '@wordpress/i18n';
import { DataForm } from '@wordpress/dataviews';
import type { Field, Form, FormField } from '@wordpress/dataviews';
import { Notice } from '@wordpress/components';
import { fetchCities, fetchMember, saveMember } from './api';
import { formatDocument, formatPhone, formatPostal } from './types';
import type { MemberDetail, MemberReseller, SelectOption } from './types';

type Draft = Record< string, string >;

function toDraft( member: MemberDetail ): Draft {
	const draft: Draft = {};
	for ( const [ key, value ] of Object.entries( member ) ) {
		draft[ key ] =
			value === null || value === undefined ? '' : String( value );
	}
	return draft;
}

interface Props {
	id: number;
	states: SelectOption[];
	primaryFocus: SelectOption[];
	statuses: SelectOption[];
	listUrl: string;
}

export default function MemberDetailView( {
	id,
	states,
	primaryFocus,
	statuses,
	listUrl,
}: Props ) {
	const [ draft, setDraft ] = useState< Draft | null >( null );
	const [ cities, setCities ] = useState< SelectOption[] >( [] );
	const [ resellers, setResellers ] = useState< MemberReseller[] >( [] );
	const [ notice, setNotice ] = useState< {
		status: 'success' | 'error';
		message: string;
	} | null >( null );

	useEffect( () => {
		fetchMember( id )
			.then( ( member ) => {
				setDraft( toDraft( member ) );
				setResellers( member.resellers ?? [] );
			} )
			.catch( ( err: { message?: string } ) =>
				setNotice( {
					status: 'error',
					message:
						err?.message ??
						__(
							'Could not load this member.',
							'axellcore-atelier'
						),
				} )
			);
	}, [ id ] );

	const uf = draft?.state ?? '';
	useEffect( () => {
		if ( ! uf ) {
			setCities( [] );
			return;
		}
		fetchCities( uf )
			.then( setCities )
			.catch( () => setCities( [] ) );
	}, [ uf ] );

	const fields: Field< Draft >[] = useMemo(
		() => [
			{
				id: 'consent',
				readOnly: true,
				label: __( 'Consent (LGPD)', 'axellcore-atelier' ),
				type: 'text',
				// HTML from the server (escaped, its links through esc_url()).
				render: ( { item } ) =>
					item.consent ? (
						<RawHTML>{ item.consent }</RawHTML>
					) : (
						__(
							'Not recorded (application sent before the consent was recorded).',
							'axellcore-atelier'
						)
					),
			},
			{
				id: 'fullname',
				readOnly: true,
				label: __( 'Full name', 'axellcore-atelier' ),
				type: 'text',
			},
			{
				id: 'email',
				readOnly: true,
				label: __( 'Professional email', 'axellcore-atelier' ),
				type: 'email',
			},
			{
				id: 'phone',
				readOnly: true,
				label: _x(
					'Phone',
					'WooCommerce field',
					'axellcore-atelier'
				),
				type: 'telephone',
				getValue: ( { item } ) => formatPhone( item.phone ?? '' ),
			},
			{
				id: 'company',
				readOnly: true,
				label: _x(
					'Company',
					'WooCommerce field',
					'axellcore-atelier'
				),
				type: 'text',
			},
			{
				id: 'professional_registration',
				readOnly: true,
				label: __(
					'Registration (CAU / CREA / ABD)',
					'axellcore-atelier'
				),
				type: 'text',
			},
			{
				id: 'primary_focus',
				readOnly: true,
				label: __( 'Main practice', 'axellcore-atelier' ),
				type: 'text',
				Edit: 'select',
				elements: primaryFocus,
			},
			{
				id: 'url',
				readOnly: true,
				label: __( 'Portfolio (URL)', 'axellcore-atelier' ),
				type: 'url',
			},
			{
				id: 'profile_type',
				readOnly: true,
				label: __( 'Registration type', 'axellcore-atelier' ),
				type: 'text',
				Edit: 'select',
				elements: [
					{
						value: 'individual',
						label: __(
							'Individual · CPF',
							'axellcore-atelier'
						),
					},
					{
						value: 'legal_entity',
						label: __( 'Company · CNPJ', 'axellcore-atelier' ),
					},
				],
			},
			{
				id: 'br_revenue_id',
				readOnly: true,
				label: __( 'CPF or CNPJ', 'axellcore-atelier' ),
				type: 'text',
				getValue: ( { item } ) =>
					formatDocument( item.br_revenue_id ?? '' ),
			},
			{
				id: 'state',
				readOnly: true,
				label: _x(
					'State / County',
					'WooCommerce field',
					'axellcore-atelier'
				),
				type: 'text',
				Edit: 'select',
				// UF is shown by its code (PR), not the state name.
				elements: states.map( ( state ) => ( {
					value: state.value,
					label: state.value,
				} ) ),
			},
			{
				id: 'city',
				readOnly: true,
				label: _x(
					'City',
					'WooCommerce field',
					'axellcore-atelier'
				),
				type: 'text',
				Edit: 'select',
				elements: cities,
			},
			{
				id: 'address_street',
				readOnly: true,
				label: _x(
					'Address line 1',
					'WooCommerce field',
					'axellcore-atelier'
				),
				type: 'text',
			},
			{
				id: 'address_number',
				readOnly: true,
				label: __( 'Number', 'axellcore-atelier' ),
				type: 'text',
			},
			{
				id: 'address_2',
				readOnly: true,
				label: _x(
					'Address line 2',
					'WooCommerce field',
					'axellcore-atelier'
				),
				type: 'text',
			},
			{
				id: 'neighborhood',
				readOnly: true,
				label: __( 'Neighborhood', 'axellcore-atelier' ),
				type: 'text',
			},
			{
				id: 'landmark',
				readOnly: true,
				label: __( 'Landmark', 'axellcore-atelier' ),
				type: 'text',
			},
			{
				id: 'postal',
				readOnly: true,
				label: _x(
					'Postcode / ZIP',
					'WooCommerce field',
					'axellcore-atelier'
				),
				type: 'text',
				getValue: ( { item } ) => formatPostal( item.postal ?? '' ),
			},
			{
				id: 'resellers',
				readOnly: true,
				label: __( 'Stores', 'axellcore-atelier' ),
				type: 'text',
				render: () =>
					resellers.length ? (
						<ul className="aa-resellers">
							{ resellers.map( ( reseller ) => (
								<li key={ reseller.field }>
									<ResellerValue reseller={ reseller } />
								</li>
							) ) }
						</ul>
					) : (
						'—'
					),
			},
			{
				id: 'status',
				label: __( 'Status', 'axellcore-atelier' ),
				type: 'text',
				Edit: 'select',
				elements: statuses,
			},
			{
				id: 'data',
				label: __( 'Submitted on', 'axellcore-atelier' ),
				type: 'text',
				readOnly: true,
			},
		],
		[ states, primaryFocus, statuses, cities, resellers ]
	);

	// Same rows as the public form (design/bootstrap/pure/adesao): fields that
	// share a line are grouped in a `row` layout inside their card.
	const row = ( rowId: string, children: string[] ) => ( {
		id: `row-${ rowId }`,
		label: '',
		children,
		layout: { type: 'row' as const },
	} );
	const card = (
		cardId: string,
		label: string,
		children: FormField[ 'children' ]
	): FormField => ( {
		id: cardId,
		label,
		children,
		layout: { type: 'card' as const, withHeader: true, isOpened: true },
	} );

	const form: Form = {
		fields: [
			card( 'status', __( 'Registration', 'axellcore-atelier' ), [
				row( 'status-1', [ 'status', 'data' ] ),
				'consent',
			] ),
			card( 'autoria', __( 'Authorship', 'axellcore-atelier' ), [
				row( 'autoria-1', [ 'fullname', 'company' ] ),
				row( 'autoria-2', [
					'email',
					'phone',
					'professional_registration',
				] ),
				row( 'autoria-3', [ 'primary_focus', 'url' ] ),
			] ),
			card( 'br_revenue_id', __( 'Document', 'axellcore-atelier' ), [
				row( 'documento-1', [ 'profile_type', 'br_revenue_id' ] ),
			] ),
			card( 'endereco', __( 'Office address', 'axellcore-atelier' ), [
				row( 'endereco-1', [
					'address_street',
					'address_number',
					'address_2',
				] ),
				row( 'endereco-2', [ 'neighborhood', 'landmark' ] ),
				row( 'endereco-3', [ 'city', 'state', 'postal' ] ),
			] ),
			card( 'lojas', __( 'Partner stores', 'axellcore-atelier' ), [
				'resellers',
			] ),
		],
	};

	if ( ! draft ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.message }
			</Notice>
		) : null;
	}

	function onChange( edits: Partial< Draft > ) {
		// The status (the member role) is the one editable field: approving
		// saves right away.
		if ( edits.status !== undefined && edits.status !== draft?.status ) {
			const status = edits.status;
			saveMember( id, { status } )
				.then( ( member ) => {
					setDraft( toDraft( member ) );
					setNotice( {
						status: 'success',
						message: __(
							'Status updated.',
							'axellcore-atelier'
						),
					} );
				} )
				.catch( ( error: { message?: string } ) =>
					setNotice( {
						status: 'error',
						message:
							error.message ||
							__( 'Could not save.', 'axellcore-atelier' ),
					} )
				);
		}
		setDraft( ( prev ) => {
			if ( ! prev ) {
				return prev;
			}
			const next: Draft = { ...prev };
			for ( const [ key, value ] of Object.entries( edits ) ) {
				next[ key ] = value ?? '';
			}
			// A different state invalidates the chosen city.
			if ( edits.state !== undefined && edits.state !== prev.state ) {
				next.city = '';
			}
			return next;
		} );
	}

	return (
		<>
			<p>
				<a href={ listUrl }>
					{ __( '← All registrations', 'axellcore-atelier' ) }
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
		</>
	);
}

/**
 * Warning sign shown next to a store whose revenda is still pending.
 */
function WarningIcon() {
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="18"
			height="18"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M12 2 1 21h22L12 2Zm0 5.5 7.5 13h-15L12 7.5Zm-1 4h2v5h-2v-5Zm0 6h2v2h-2v-2Z" />
		</svg>
	);
}

/**
 * A partner store: its text and, for a store waiting for curadoria, a
 * warning with the link to it.
 * @param root0
 * @param root0.reseller
 */
function ResellerValue( { reseller }: { reseller?: MemberReseller } ) {
	if ( ! reseller ) {
		return null;
	}
	return (
		<span className="aa-reseller">
			<span>{ reseller.title }</span>
			{ reseller.pending && (
				<>
					{ ' ' }
					<span
						className="aa-reseller-pending"
						role="img"
						aria-label={ __(
							'Pending curation',
							'axellcore-atelier'
						) }
						title={ __(
							'Pending curation',
							'axellcore-atelier'
						) }
					>
						<WarningIcon />
					</span>{ ' ' }
					{ reseller.url && (
						<a href={ reseller.url }>
							{ __( 'Open store', 'axellcore-atelier' ) }
						</a>
					) }
				</>
			) }
		</span>
	);
}
