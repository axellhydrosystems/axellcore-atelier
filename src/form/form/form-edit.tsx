import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { parse } from '@wordpress/blocks';
import type { BlockEditProps } from '@wordpress/blocks';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { FormAttributes } from './types';

export const ALLOWED_BLOCKS = [
	'core/heading',
	'core/paragraph',
	'core/group',
	'core/columns',
	'core/column',
	'core/list',
	'core/list-item',
	'core/buttons',
	'core/button',
	'axell/fieldset',
	'axell/form-group',
	'axell/form-check',
	'axell/form-submission-notification',
];

interface FormEditOptions {
	/** The store post type is fixed by the block (shown, not editable). */
	lockedStore?: boolean;
	/** Block markup inserted on a fresh insert (the atelier template). */
	initialMarkup?: string;
}

const STATUS_OPTIONS = [
	{ label: __( 'Pendente', 'axellcore-atelierclub' ), value: 'pending' },
	{ label: __( 'Rascunho', 'axellcore-atelierclub' ), value: 'draft' },
	{ label: __( 'Publicado', 'axellcore-atelierclub' ), value: 'publish' },
	{ label: __( 'Privado', 'axellcore-atelierclub' ), value: 'private' },
];

/** Short random id for formId. */
const newFormId = () => Math.random().toString( 36 ).slice( 2, 10 );

/**
 * Editor of a form block: its inner blocks and the "Ações de envio" panel
 * (store as a post of a chosen type, send an email). Shared by axell/form and
 * axell/form-atelier.
 *
 * @param props   Block edit props.
 * @param options Per-block options.
 */
export function FormEdit(
	props: BlockEditProps< FormAttributes >,
	options: FormEditOptions = {}
) {
	const { attributes, setAttributes, clientId } = props;
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

	const { postTypes, duplicated, hasInner } = useSelect(
		( select ) => {
			const be = select( blockEditorStore ) as unknown as {
				getSettings: () => Record< string, unknown >;
				getClientIdsWithDescendants: () => string[];
				getBlockAttributes: ( id: string ) => Record< string, unknown > | null;
				getBlockCount: ( id: string ) => number;
			};
			const others = be
				.getClientIdsWithDescendants()
				.filter( ( id ) => id !== clientId )
				.some( ( id ) => be.getBlockAttributes( id )?.formId === attributes.formId );
			return {
				// core/block-editor only keeps a fixed list of setting keys; the
				// editor settings keep the custom one (added in PHP).
				postTypes: ( ( select( 'core/editor' ) as unknown as {
					getEditorSettings: () => Record< string, unknown >;
				} ).getEditorSettings().axellFormPostTypes || [] ) as { label: string; value: string }[],
				duplicated: !! attributes.formId && others,
				hasInner: be.getBlockCount( clientId ) > 0,
			};
		},
		[ clientId, attributes.formId ]
	);

	// A form needs its own id in the page (a new insert, or a copy).
	useEffect( () => {
		if ( ! attributes.formId || duplicated ) {
			setAttributes( { formId: newFormId() } );
		}
	}, [ attributes.formId, duplicated ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// The atelier form starts with its full template.
	useEffect( () => {
		if ( options.initialMarkup && ! hasInner ) {
			replaceInnerBlocks( clientId, parse( options.initialMarkup ), false );
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		template: options.initialMarkup ? undefined : [ [ 'axell/form-group', {} ] ],
		templateLock: false,
	} );

	const storeLabel =
		postTypes.find( ( t ) => t.value === attributes.storePostType )?.label || attributes.storePostType;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Ações de envio', 'axellcore-atelierclub' ) } initialOpen>
					<SelectControl
						label={ __( 'Gravar em', 'axellcore-atelierclub' ) }
						help={
							options.lockedStore
								? __( 'Definido por este bloco: cada envio vira um cadastro.', 'axellcore-atelierclub' )
								: __( 'Cada envio vira um post deste tipo. Nenhum: não grava.', 'axellcore-atelierclub' )
						}
						value={ attributes.storePostType }
						disabled={ !! options.lockedStore }
						options={
							options.lockedStore
								? [ { label: storeLabel, value: attributes.storePostType } ]
								: [ { label: __( 'Nenhum', 'axellcore-atelierclub' ), value: '' }, ...postTypes ]
						}
						onChange={ ( value: string ) => setAttributes( { storePostType: value } ) }
					/>
					{ !! attributes.storePostType && ! options.lockedStore && (
						<>
							<SelectControl
								label={ __( 'Status do post', 'axellcore-atelierclub' ) }
								value={ attributes.storeStatus }
								options={ STATUS_OPTIONS }
								onChange={ ( value: string ) => setAttributes( { storeStatus: value } ) }
							/>
							<TextControl
								label={ __( 'Campo do título (name)', 'axellcore-atelierclub' ) }
								help={ __( 'Name do campo cujo valor vira o título do post.', 'axellcore-atelierclub' ) }
								value={ attributes.titleField }
								onChange={ ( value: string ) => setAttributes( { titleField: value } ) }
							/>
						</>
					) }
					<ToggleControl
						label={ __( 'Enviar e-mail', 'axellcore-atelierclub' ) }
						checked={ !! attributes.sendEmail }
						onChange={ ( value: boolean ) => setAttributes( { sendEmail: value } ) }
					/>
					{ attributes.sendEmail && (
						<>
							<TextControl
								label={ __( 'Para', 'axellcore-atelierclub' ) }
								help={ __( 'Separe vários e-mails por vírgula. Em branco: e-mail do administrador.', 'axellcore-atelierclub' ) }
								value={ attributes.emailTo }
								onChange={ ( value: string ) => setAttributes( { emailTo: value } ) }
							/>
							<TextControl
								label={ __( 'Assunto', 'axellcore-atelierclub' ) }
								value={ attributes.emailSubject }
								onChange={ ( value: string ) => setAttributes( { emailSubject: value } ) }
							/>
							<TextareaControl
								label={ __( 'Mensagem', 'axellcore-atelierclub' ) }
								help={ __( 'Tags: {name de um campo}, por exemplo {nome}, e {all_fields} com todos os campos.', 'axellcore-atelierclub' ) }
								value={ attributes.emailBody }
								rows={ 6 }
								onChange={ ( value: string ) => setAttributes( { emailBody: value } ) }
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>
			<form { ...innerBlocksProps } />
		</>
	);
}
