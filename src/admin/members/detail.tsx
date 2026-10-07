import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataForm } from '@wordpress/dataviews';
import type { Field, Form, FormField } from '@wordpress/dataviews';
import { Notice } from '@wordpress/components';
import { fetchCities, fetchMember, saveMember } from './api';
import { formatDocument } from './types';
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
	const [ isSaving, setIsSaving ] = useState( false );
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
							'axellcore-atelierclub'
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
				id: 'fullname',
				readOnly: true,
				label: __( 'Nome completo', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'email',
				readOnly: true,
				label: __( 'E-mail profissional', 'axellcore-atelierclub' ),
				type: 'email',
			},
			{
				id: 'phone',
				readOnly: true,
				label: __( 'Telefone', 'axellcore-atelierclub' ),
				type: 'telephone',
			},
			{
				id: 'company',
				readOnly: true,
				label: __( 'Escritório / Atelê', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'professional_registration',
				readOnly: true,
				label: __(
					'Registro (CAU / CREA / ABD)',
					'axellcore-atelierclub'
				),
				type: 'text',
			},
			{
				id: 'primary_focus',
				readOnly: true,
				label: __( 'Atuação principal', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: primaryFocus,
			},
			{
				id: 'url',
				readOnly: true,
				label: __( 'Portfólio (URL)', 'axellcore-atelierclub' ),
				type: 'url',
			},
			{
				id: 'profile_type',
				readOnly: true,
				label: __( 'Tipo de cadastro', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: [
					{
						value: 'individual',
						label: __(
							'Pessoa Física · CPF',
							'axellcore-atelierclub'
						),
					},
					{
						value: 'legal_entity',
						label: __(
							'Pessoa Jurídica · CNPJ',
							'axellcore-atelierclub'
						),
					},
				],
			},
			{
				id: 'br_revenue_id',
				readOnly: true,
				label: __( 'CPF ou CNPJ', 'axellcore-atelierclub' ),
				type: 'text',
				getValue: ( { item } ) => formatDocument( item.br_revenue_id ?? '' ),
			},
			{
				id: 'state',
				readOnly: true,
				label: __( 'UF', 'axellcore-atelierclub' ),
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
				label: __( 'Cidade', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: cities,
			},
			{
				id: 'address_street',
				readOnly: true,
				label: __( 'Logradouro', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'address_number',
				readOnly: true,
				label: __( 'Número', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'address_2',
				readOnly: true,
				label: __( 'Complemento', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'neighborhood',
				readOnly: true,
				label: __( 'Bairro', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'landmark',
				readOnly: true,
				label: __( 'Referência', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'postal',
				readOnly: true,
				label: __( 'CEP', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'reseller1',
				readOnly: true,
				label: __( 'Loja parceira 1', 'axellcore-atelierclub' ),
				type: 'text',
				render: () => <ResellerValue reseller={ resellers.find( ( r ) => r.field === 'reseller1' ) } />,
			},
			{
				id: 'reseller2',
				readOnly: true,
				label: __( 'Loja parceira 2', 'axellcore-atelierclub' ),
				type: 'text',
				render: () => <ResellerValue reseller={ resellers.find( ( r ) => r.field === 'reseller2' ) } />,
			},
			{
				id: 'reseller3',
				readOnly: true,
				label: __( 'Loja parceira 3', 'axellcore-atelierclub' ),
				type: 'text',
				render: () => <ResellerValue reseller={ resellers.find( ( r ) => r.field === 'reseller3' ) } />,
			},
			{
				id: 'reseller4',
				readOnly: true,
				label: __( 'Loja parceira 4', 'axellcore-atelierclub' ),
				type: 'text',
				render: () => <ResellerValue reseller={ resellers.find( ( r ) => r.field === 'reseller4' ) } />,
			},
			{
				id: 'reseller5',
				readOnly: true,
				label: __( 'Loja parceira 5', 'axellcore-atelierclub' ),
				type: 'text',
				render: () => <ResellerValue reseller={ resellers.find( ( r ) => r.field === 'reseller5' ) } />,
			},
			{
				id: 'status',
				label: __( 'Status', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: statuses,
			},
			{
				id: 'data',
				label: __( 'Enviado em', 'axellcore-atelierclub' ),
				type: 'text',
				readOnly: true,
			},
		],
		[ states, primaryFocus, statuses, cities, resellers ]
	);

	// Same rows as the public form (design/bootstrap/pure/adesao): fields that
	// share a line are grouped in a `row` layout inside their card.
	const row = ( id: string, children: string[] ) => ( {
		id: `row-${ id }`,
		label: '',
		children,
		layout: { type: 'row' as const },
	} );
	const card = ( id: string, label: string, children: FormField[ 'children' ] ): FormField => ( {
		id,
		label,
		children,
		layout: { type: 'card' as const, withHeader: true, isOpened: true },
	} );

	const form: Form = {
		fields: [
			card( 'status', __( 'Cadastro', 'axellcore-atelierclub' ), [
				row( 'status-1', [ 'status', 'data' ] ),
			] ),
			card( 'autoria', __( 'Autoria', 'axellcore-atelierclub' ), [
				row( 'autoria-1', [ 'fullname', 'company' ] ),
				row( 'autoria-2', [ 'email', 'phone', 'professional_registration' ] ),
				row( 'autoria-3', [ 'primary_focus', 'url' ] ),
			] ),
			card( 'br_revenue_id', __( 'Documento', 'axellcore-atelierclub' ), [
				row( 'documento-1', [ 'profile_type', 'br_revenue_id' ] ),
			] ),
			card( 'endereco', __( 'Endereço do escritório', 'axellcore-atelierclub' ), [
				row( 'endereco-1', [ 'address_street', 'address_number', 'address_2' ] ),
				row( 'endereco-2', [ 'neighborhood', 'landmark' ] ),
				row( 'endereco-3', [ 'city', 'state', 'postal' ] ),
			] ),
			...( resellers.length
				? [ card( 'resellers', __( 'Lojas parceiras', 'axellcore-atelierclub' ), resellers.map( ( r ) => r.field ) ) ]
				: [] ),
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
						message: __( 'Status atualizado.', 'axellcore-atelierclub' ),
					} );
				} )
				.catch( ( error: { message?: string } ) =>
					setNotice( {
						status: 'error',
						message: error.message || __( 'Não foi possível salvar.', 'axellcore-atelierclub' ),
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
					{ __( '← Todos os cadastros', 'axellcore-atelierclub' ) }
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
						aria-label={ __( 'Pendente de curadoria', 'axellcore-atelierclub' ) }
						title={ __( 'Pendente de curadoria', 'axellcore-atelierclub' ) }
					>
						<WarningIcon />
					</span>{ ' ' }
					{ reseller.url && (
						<a href={ reseller.url }>
							{ __( 'Abrir loja', 'axellcore-atelierclub' ) }
						</a>
					) }
				</>
			) }
		</span>
	);
}
