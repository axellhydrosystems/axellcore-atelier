import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import { cityMarkup, chosenCountry } from '../form-address/markup';
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
					<SelectControl
						label={ __( 'País definido por', 'axellcore-atelierclub' ) }
						value={ ( attributes.countrySource as string ) || 'field' }
						options={ [
							{ label: __( 'Campo', 'axellcore-atelierclub' ), value: 'field' },
							{ label: __( 'Seleção', 'axellcore-atelierclub' ), value: 'select' },
						] as { label: string; value: string }[] }
						onChange={ ( value: string ) =>
							setAttributes( value === 'select'
								? { countrySource: value, country: chosenCountry( attributes.country as string | undefined ) }
								: { countrySource: value } )
						}
					/>
					{ ( attributes.countrySource || 'field' ) === 'field' ? (
						<TextControl
							label={ __( 'Campo do país (name)', 'axellcore-atelierclub' ) }
							value={ ( attributes.countryField as string ) || '' }
							onChange={ ( value: string ) => setAttributes( { countryField: value } ) }
						/>
					) : (
						<SelectControl
							label={ __( 'País', 'axellcore-atelierclub' ) }
							value={ chosenCountry( attributes.country as string | undefined ) }
							options={ [
								{ label: 'Brasil', value: 'BR' },
								{ label: 'Estados Unidos', value: 'US' },
								{ label: __( 'Outro', 'axellcore-atelierclub' ), value: '' },
							] as { label: string; value: string }[] }
							onChange={ ( value: string ) => setAttributes( { country: value } ) }
						/>
					) }
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
			{ cityMarkup( { blockProps, id: attributes.id || undefined, name: attributes.name, placeholder: attributes.placeholder, required: attributes.required, stateField: attributes.stateField as string, searchable: !! attributes.searchable, countryField: attributes.countryField as string, countrySource: attributes.countrySource as string, country: attributes.country as string, isEditor: true } ) }
		</>
	);
}
