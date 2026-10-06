import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import { cityMarkup } from '../form-address/markup';
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
					<TextControl
						label={ __( 'Campo do estado (name)', 'axellcore-atelierclub' ) }
						value={ ( attributes.stateField as string ) || '' }
						onChange={ ( value: string ) => setAttributes( { stateField: value } ) }
					/>
					<TextControl
						label={ __( 'Campo do país (name)', 'axellcore-atelierclub' ) }
						value={ ( attributes.countryField as string ) || '' }
						onChange={ ( value: string ) => setAttributes( { countryField: value } ) }
					/>
					<ToggleControl
						label={ __( 'Busca com autocomplete', 'axellcore-atelierclub' ) }
						help={ __( 'No Brasil, digita-se o nome e escolhe-se a cidade da UF numa lista filtrada, em vez de um select.', 'axellcore-atelierclub' ) }
						checked={ !! attributes.searchable }
						onChange={ ( value: boolean ) => setAttributes( { searchable: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelierclub' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ cityMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, stateField: attributes.stateField as string, searchable: !! attributes.searchable, countryField: attributes.countryField as string } ) }
		</>
	);
}
