import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import { autocompleteMarkup } from '../form-control/autocomplete-markup';
import { RESELLER_POST_TYPE, RESELLER_TEMPLATE } from './constants';
import type { ResellerAttributes } from './save';

export default function Edit( { attributes, setAttributes }: BlockEditProps< ResellerAttributes > ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Campo', 'axellcore-atelierclub' ) } initialOpen>
					<TextControl
						label={ __( 'ID', 'axellcore-atelierclub' ) }
						value={ attributes.id }
						onChange={ ( value: string ) => setAttributes( { id: value } ) }
					/>
					<TextControl
						label={ __( 'Name (name attribute)', 'axellcore-atelierclub' ) }
						help={ __( 'Em branco usa o ID.', 'axellcore-atelierclub' ) }
						value={ attributes.name }
						onChange={ ( value: string ) => setAttributes( { name: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'axellcore-atelierclub' ) }
						value={ attributes.placeholder }
						onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelierclub' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
					<ToggleControl
						label={ __( 'Aceitar "Adicionar não encontrada"', 'axellcore-atelierclub' ) }
						help={ __( 'Mostra a opção e os campos Nome, UF e Cidade para cadastrar uma revenda nova.', 'axellcore-atelierclub' ) }
						checked={ !! attributes.allowNotFound }
						onChange={ ( value: boolean ) => setAttributes( { allowNotFound: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ autocompleteMarkup( {
				blockProps,
				isSave: false,
				id: attributes.id || undefined,
				name: attributes.name || attributes.id,
				placeholder: attributes.placeholder || undefined,
				postType: RESELLER_POST_TYPE,
				template: RESELLER_TEMPLATE,
				allowNotFound: !! attributes.allowNotFound,
			} ) }
		</>
	);
}
