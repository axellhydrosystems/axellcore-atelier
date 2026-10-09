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
				<PanelBody title={ __( 'Field', 'axellcore-atelier' ) } initialOpen>
					<TextControl
						label={ __( 'ID', 'axellcore-atelier' ) }
						value={ attributes.id }
						onChange={ ( value: string ) => setAttributes( { id: value } ) }
					/>
					<TextControl
						label={ __( 'Name (name attribute)', 'axellcore-atelier' ) }
						help={ __( 'Empty uses the ID.', 'axellcore-atelier' ) }
						value={ attributes.name }
						onChange={ ( value: string ) => setAttributes( { name: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'axellcore-atelier' ) }
						value={ attributes.placeholder }
						onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelier' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
					<TextControl
						label={ __( 'Type field (name)', 'axellcore-atelier' ) }
						help={ __( 'Name of the field that sets CPF or CNPJ (values cpf or cnpj), for example tipoDoc. Empty: the type comes from the number\'s length.', 'axellcore-atelier' ) }
						value={ attributes.typeField || '' }
						onChange={ ( value: string ) => setAttributes( { typeField: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ documentMarkup( attributes, blockProps, '', true ) }
		</>
	);
}
