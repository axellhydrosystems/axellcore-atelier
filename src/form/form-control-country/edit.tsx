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
				<PanelBody title={ __( 'Field', 'axellcore-atelierclub' ) } initialOpen>
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
						label={ __( 'Country', 'axellcore-atelierclub' ) }
						help={
							attributes.hiddenField
								? __( 'Value sent in the hidden field.', 'axellcore-atelierclub' )
								: __( 'Country already selected when the form opens.', 'axellcore-atelierclub' )
						}
						value={ ( attributes.fixed as string ) || '' }
						options={ [
							{ label: __( 'None', 'axellcore-atelierclub' ), value: '' },
							{ label: 'Brasil', value: 'BR' },
							{ label: 'Estados Unidos', value: 'US' },
						] as { label: string; value: string }[] }
						onChange={ ( value: string ) => setAttributes( { fixed: value } ) }
					/>
					<ToggleControl
						label={ __( 'Hidden', 'axellcore-atelierclub' ) }
						help={
							attributes.hiddenField && ! attributes.fixed
								? __( 'Choose a country for the hidden field.', 'axellcore-atelierclub' )
								: __( 'Sends the country in a hidden field, without the select.', 'axellcore-atelierclub' )
						}
						checked={ !! attributes.hiddenField }
						onChange={ ( value: boolean ) => setAttributes( { hiddenField: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelierclub' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ countryMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, fixed: attributes.fixed as string, hiddenField: !! attributes.hiddenField, isEditor: true } ) }
		</>
	);
}
