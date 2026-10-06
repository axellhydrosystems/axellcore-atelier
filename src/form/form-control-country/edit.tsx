import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import { countryMarkup } from '../form-address/markup';
import type { AddressAttributes } from '../form-address/attributes';

export default function Edit( { attributes, setAttributes }: BlockEditProps< AddressAttributes > ) {
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
						value={ attributes.name }
						onChange={ ( value: string ) => setAttributes( { name: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'axellcore-atelierclub' ) }
						value={ attributes.placeholder }
						onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
					/>
					<SelectControl
						label={ __( 'País fixo', 'axellcore-atelierclub' ) }
						help={ __( 'Com um país fixo, o campo é enviado oculto e o select não aparece.', 'axellcore-atelierclub' ) }
						value={ ( attributes.fixed as string ) || '' }
						options={ [
							{ label: __( 'Nenhum (o visitante escolhe)', 'axellcore-atelierclub' ), value: '' },
							{ label: 'Brasil', value: 'BR' },
							{ label: 'Estados Unidos', value: 'US' },
						] as { label: string; value: string }[] }
						onChange={ ( value: string ) => setAttributes( { fixed: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelierclub' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ countryMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, fixed: attributes.fixed as string, isEditor: true } ) }
		</>
	);
}
