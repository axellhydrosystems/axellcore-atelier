import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataForm } from '@wordpress/dataviews';
import type { Field, Form, FormField } from '@wordpress/dataviews';
import { Notice } from '@wordpress/components';
import { fetchCities, fetchMember, saveMember } from './api';
import type { MemberDetail, MemberLoja, SelectOption } from './types';

type Draft = Record< string, string >;

const EDITABLE_KEYS = [
	'nome',
	'email',
	'portfolio',
	'uf',
	'cidade',
	'escritorio',
	'telefone',
	'registro',
	'atuacao',
	'tipoDoc',
	'documento',
	'rua',
	'numero',
	'complemento',
	'bairro',
	'referencia',
	'cep',
	'loja1',
	'loja2',
	'loja3',
	'loja4',
] as const;

function toDraft( member: MemberDetail ): Draft {
	const draft: Draft = {};
	for ( const [ key, value ] of Object.entries( member ) ) {
		draft[ key ] =
			value === null || value === undefined ? '' : String( value );
	}
	return draft;
}

function toPayload( draft: Draft ): Draft {
	const payload: Draft = {};
	for ( const key of EDITABLE_KEYS ) {
		payload[ key ] = draft[ key ] ?? '';
	}
	return payload;
}

interface Props {
	id: number;
	states: SelectOption[];
	atuacao: string[];
	listUrl: string;
}

export default function MemberDetailView( {
	id,
	states,
	atuacao,
	listUrl,
}: Props ) {
	const [ draft, setDraft ] = useState< Draft | null >( null );
	const [ cities, setCities ] = useState< SelectOption[] >( [] );
	const [ lojas, setLojas ] = useState< MemberLoja[] >( [] );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState< {
		status: 'success' | 'error';
		message: string;
	} | null >( null );

	useEffect( () => {
		fetchMember( id )
			.then( ( member ) => {
				setDraft( toDraft( member ) );
				setLojas( member.lojas ?? [] );
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

	const uf = draft?.uf ?? '';
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
				id: 'nome',
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
				id: 'telefone',
				readOnly: true,
				label: __( 'Telefone', 'axellcore-atelierclub' ),
				type: 'telephone',
			},
			{
				id: 'escritorio',
				readOnly: true,
				label: __( 'Escritório / Atelê', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'registro',
				readOnly: true,
				label: __(
					'Registro (CAU / CREA / ABD)',
					'axellcore-atelierclub'
				),
				type: 'text',
			},
			{
				id: 'atuacao',
				readOnly: true,
				label: __( 'Atuação principal', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: atuacao.map( ( value ) => ( {
					value,
					label: value,
				} ) ),
			},
			{
				id: 'portfolio',
				readOnly: true,
				label: __( 'Portfólio (URL)', 'axellcore-atelierclub' ),
				type: 'url',
			},
			{
				id: 'tipoDoc',
				readOnly: true,
				label: __( 'Tipo de cadastro', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: [
					{
						value: 'cpf',
						label: __(
							'Pessoa Física · CPF',
							'axellcore-atelierclub'
						),
					},
					{
						value: 'cnpj',
						label: __(
							'Pessoa Jurídica · CNPJ',
							'axellcore-atelierclub'
						),
					},
				],
			},
			{
				id: 'documento',
				readOnly: true,
				label: __( 'CPF ou CNPJ', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'uf',
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
				id: 'cidade',
				readOnly: true,
				label: __( 'Cidade', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: cities,
			},
			{
				id: 'rua',
				readOnly: true,
				label: __( 'Logradouro', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'numero',
				readOnly: true,
				label: __( 'Número', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'complemento',
				readOnly: true,
				label: __( 'Complemento', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'bairro',
				readOnly: true,
				label: __( 'Bairro', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'referencia',
				readOnly: true,
				label: __( 'Referência', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'cep',
				readOnly: true,
				label: __( 'CEP', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'loja1',
				readOnly: true,
				label: __( 'Loja parceira 1', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'loja2',
				readOnly: true,
				label: __( 'Loja parceira 2', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'loja3',
				readOnly: true,
				label: __( 'Loja parceira 3', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'loja4',
				readOnly: true,
				label: __( 'Loja parceira 4', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'status',
				label: __( 'Status', 'axellcore-atelierclub' ),
				type: 'text',
				readOnly: true,
			},
			{
				id: 'data',
				label: __( 'Enviado em', 'axellcore-atelierclub' ),
				type: 'text',
				readOnly: true,
			},
		],
		[ states, atuacao, cities ]
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
			card( 'autoria', __( 'Autoria', 'axellcore-atelierclub' ), [
				row( 'autoria-1', [ 'nome', 'escritorio' ] ),
				row( 'autoria-2', [ 'email', 'telefone', 'registro' ] ),
				row( 'autoria-3', [ 'atuacao', 'portfolio' ] ),
			] ),
			card( 'documento', __( 'Documento', 'axellcore-atelierclub' ), [
				row( 'documento-1', [ 'tipoDoc', 'documento' ] ),
			] ),
			card( 'endereco', __( 'Endereço do escritório', 'axellcore-atelierclub' ), [
				row( 'endereco-1', [ 'rua', 'numero', 'complemento' ] ),
				row( 'endereco-2', [ 'bairro', 'referencia' ] ),
				row( 'endereco-3', [ 'cidade', 'uf', 'cep' ] ),
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
		setDraft( ( prev ) => {
			if ( ! prev ) {
				return prev;
			}
			const next: Draft = { ...prev };
			for ( const [ key, value ] of Object.entries( edits ) ) {
				next[ key ] = value ?? '';
			}
			// A different state invalidates the chosen city.
			if ( edits.uf !== undefined && edits.uf !== prev.uf ) {
				next.cidade = '';
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
			<LojasParceiras lojas={ lojas } />
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
 * The partner stores of the member. A store linked to an revenda that is
 * still pending shows a warning and a link to open it for curation.
 * @param root0
 * @param root0.lojas
 */
function LojasParceiras( { lojas }: { lojas: MemberLoja[] } ) {
	if ( ! lojas.length ) {
		return null;
	}
	return (
		<section className="aac-lojas-parceiras">
			<h3>{ __( 'Lojas parceiras', 'axellcore-atelierclub' ) }</h3>
			<ul>
				{ lojas.map( ( loja ) => (
					<li key={ loja.field }>
						<span>{ loja.title }</span>
						{ loja.pending && (
							<>
								{ ' ' }
								<span
									className="aac-loja-pendente"
									role="img"
									aria-label={ __( 'Pendente de curadoria', 'axellcore-atelierclub' ) }
									title={ __( 'Pendente de curadoria', 'axellcore-atelierclub' ) }
								>
									<WarningIcon />
								</span>{ ' ' }
								{ loja.url && (
									<a href={ loja.url }>
										{ __( 'Abrir loja', 'axellcore-atelierclub' ) }
									</a>
								) }
							</>
						) }
					</li>
				) ) }
			</ul>
		</section>
	);
}
