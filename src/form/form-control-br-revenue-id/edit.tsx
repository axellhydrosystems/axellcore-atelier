import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import { documentMarkup, type BrDocumentAttributes } from './markup';

export default function Edit( { attributes, setAttributes }: BlockEditProps< BrDocumentAttributes > ) {
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
					<TextControl
						label={ __( 'Campo do tipo (name)', 'axellcore-atelierclub' ) }
						help={ __( 'Name do campo que define CPF ou CNPJ (valores cpf ou cnpj), por exemplo tipoDoc. Em branco, o tipo sai pelo tamanho do número.', 'axellcore-atelierclub' ) }
						value={ attributes.typeField || '' }
						onChange={ ( value: string ) => setAttributes( { typeField: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ documentMarkup( attributes, blockProps, '', true ) }
		</>
	);
}
