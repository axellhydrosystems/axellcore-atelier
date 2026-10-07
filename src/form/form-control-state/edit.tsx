import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import { stateMarkup, chosenCountry } from '../form-address/markup';
import type { AddressAttributes } from '../form-address/attributes';

export default function Edit( { attributes, setAttributes }: BlockEditProps< AddressAttributes > ) {
	const blockProps = useBlockProps();
	// "Seleção" stores its country (older blocks relied on a default).
	useEffect( () => {
		if ( attributes.countrySource === 'select' && attributes.country === undefined ) {
			setAttributes( { country: 'BR' } );
		}
	}, [ attributes.countrySource, attributes.country ] ); // eslint-disable-line react-hooks/exhaustive-deps

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
						label={ __( 'Country set by', 'axellcore-atelierclub' ) }
						value={ ( attributes.countrySource as string ) || 'field' }
						options={ [
							{ label: __( 'Field', 'axellcore-atelierclub' ), value: 'field' },
							{ label: __( 'Selection', 'axellcore-atelierclub' ), value: 'select' },
						] as { label: string; value: string }[] }
						onChange={ ( value: string ) =>
							setAttributes( value === 'select'
								? { countrySource: value, country: chosenCountry( attributes.country as string | undefined ) }
								: { countrySource: value } )
						}
					/>
					{ ( attributes.countrySource || 'field' ) === 'field' ? (
						<TextControl
							label={ __( 'Country field (name)', 'axellcore-atelierclub' ) }
							value={ ( attributes.countryField as string ) || '' }
							onChange={ ( value: string ) => setAttributes( { countryField: value } ) }
						/>
					) : (
						<SelectControl
							label={ __( 'Country', 'axellcore-atelierclub' ) }
							value={ chosenCountry( attributes.country as string | undefined ) }
							options={ [
								{ label: 'Brasil', value: 'BR' },
								{ label: 'Estados Unidos', value: 'US' },
								{ label: __( 'Other', 'axellcore-atelierclub' ), value: '' },
							] as { label: string; value: string }[] }
							onChange={ ( value: string ) => setAttributes( { country: value } ) }
						/>
					) }
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelierclub' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ stateMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, countryField: attributes.countryField as string, countrySource: attributes.countrySource as string, country: attributes.country as string, isEditor: true } ) }
		</>
	);
}
