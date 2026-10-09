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
				<PanelBody title={ __( 'Field', 'axellcore-atelier' ) } initialOpen>
					<TextControl
						label={ __( 'ID', 'axellcore-atelier' ) }
						value={ attributes.id }
						onChange={ ( value: string ) => setAttributes( { id: value } ) }
					/>
					<TextControl
						label={ __( 'Name (name attribute)', 'axellcore-atelier' ) }
						value={ attributes.name }
						onChange={ ( value: string ) => setAttributes( { name: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'axellcore-atelier' ) }
						value={ attributes.placeholder }
						onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
					/>
					<SelectControl
						label={ __( 'Country', 'axellcore-atelier' ) }
						help={
							attributes.hiddenField
								? __( 'Value sent in the hidden field.', 'axellcore-atelier' )
								: __( 'Country already selected when the form opens.', 'axellcore-atelier' )
						}
						value={ ( attributes.fixed as string ) || '' }
						options={ [
							{ label: __( 'None', 'axellcore-atelier' ), value: '' },
							{ label: 'Brasil', value: 'BR' },
							{ label: 'Estados Unidos', value: 'US' },
						] as { label: string; value: string }[] }
						onChange={ ( value: string ) => setAttributes( { fixed: value } ) }
					/>
					<ToggleControl
						label={ __( 'Hidden', 'axellcore-atelier' ) }
						help={
							attributes.hiddenField && ! attributes.fixed
								? __( 'Choose a country for the hidden field.', 'axellcore-atelier' )
								: __( 'Sends the country in a hidden field, without the select.', 'axellcore-atelier' )
						}
						checked={ !! attributes.hiddenField }
						onChange={ ( value: boolean ) => setAttributes( { hiddenField: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelier' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ countryMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, fixed: attributes.fixed as string, hiddenField: !! attributes.hiddenField, isEditor: true } ) }
		</>
	);
}
