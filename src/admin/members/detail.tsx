import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataForm } from '@wordpress/dataviews';
import type { Field, Form } from '@wordpress/dataviews';
import { Button, Notice } from '@wordpress/components';
import { fetchCities, fetchMember, saveMember } from './api';
import type { MemberDetail, SelectOption } from './types';

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
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState< {
		status: 'success' | 'error';
		message: string;
	} | null >( null );

	useEffect( () => {
		fetchMember( id )
			.then( ( member ) => setDraft( toDraft( member ) ) )
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
				label: __( 'Nome completo', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'email',
				label: __( 'E-mail profissional', 'axellcore-atelierclub' ),
				type: 'email',
			},
			{
				id: 'telefone',
				label: __( 'Telefone', 'axellcore-atelierclub' ),
				type: 'telephone',
			},
			{
				id: 'escritorio',
				label: __( 'Escritório / Atelê', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'registro',
				label: __(
					'Registro (CAU / CREA / ABD)',
					'axellcore-atelierclub'
				),
				type: 'text',
			},
			{
				id: 'atuacao',
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
				label: __( 'Portfólio (URL)', 'axellcore-atelierclub' ),
				type: 'url',
			},
			{
				id: 'tipoDoc',
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
				label: __( 'CPF ou CNPJ', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'uf',
				label: __( 'UF', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: states,
			},
			{
				id: 'cidade',
				label: __( 'Cidade', 'axellcore-atelierclub' ),
				type: 'text',
				Edit: 'select',
				elements: cities,
			},
			{
				id: 'rua',
				label: __( 'Logradouro', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'numero',
				label: __( 'Número', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'complemento',
				label: __( 'Complemento', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'bairro',
				label: __( 'Bairro', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'referencia',
				label: __( 'Referência', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'cep',
				label: __( 'CEP', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'loja1',
				label: __( 'Loja parceira 1', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'loja2',
				label: __( 'Loja parceira 2', 'axellcore-atelierclub' ),
				type: 'text',
			},
			{
				id: 'loja3',
				label: __( 'Loja parceira 3', 'axellcore-atelierclub' ),
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

	const form: Form = {
		fields: [
			'nome',
			'email',
			'telefone',
			'escritorio',
			'registro',
			'atuacao',
			'portfolio',
			'tipoDoc',
			'documento',
			'uf',
			'cidade',
			'rua',
			'numero',
			'complemento',
			'bairro',
			'referencia',
			'cep',
			'loja1',
			'loja2',
			'loja3',
			'status',
			'data',
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

	function onSave() {
		if ( ! draft ) {
			return;
		}
		setIsSaving( true );
		setNotice( null );
		saveMember( id, toPayload( draft ) )
			.then( ( member ) => {
				setDraft( toDraft( member ) );
				setNotice( {
					status: 'success',
					message: __( 'Cadastro salvo.', 'axellcore-atelierclub' ),
				} );
			} )
			.catch( ( err: { message?: string } ) =>
				setNotice( {
					status: 'error',
					message:
						err?.message ??
						__(
							'Could not save this member.',
							'axellcore-atelierclub'
						),
				} )
			)
			.finally( () => setIsSaving( false ) );
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
			<Button
				variant="primary"
				onClick={ onSave }
				isBusy={ isSaving }
				disabled={ isSaving }
			>
				{ __( 'Salvar', 'axellcore-atelierclub' ) }
			</Button>
		</>
	);
}
