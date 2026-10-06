import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { FormControlAttributes, FormControlOption } from './types';
import ControlElement from './control-element';
import './editor.scss';

const MASK_OPTIONS = [
	{ label: __( '— none —', 'axellcore-atelierclub' ), value: '' },
	{ label: __( 'CPF / CNPJ', 'axellcore-atelierclub' ), value: 'cpf-cnpj' },
	{ label: __( 'CEP (postal code)', 'axellcore-atelierclub' ), value: 'cep' },
	{ label: __( 'Phone', 'axellcore-atelierclub' ), value: 'phone' },
];

function optionsToText( options: FormControlOption[] ): string {
	return ( options || [] )
		.map( ( o ) => `${ o.label || '' }|${ o.value || '' }` )
		.join( '\n' );
}

function textToOptions( text: string ): FormControlOption[] {
	return text
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.map( ( line ) => {
			const parts = line.split( '|' );
			return {
				label: ( parts[ 0 ] || '' ).trim(),
				value: ( parts[ 1 ] !== undefined
					? parts[ 1 ]
					: parts[ 0 ] || ''
				).trim(),
			};
		} );
}

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FormControlAttributes > ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Control', 'axellcore-atelierclub' ) }
					initialOpen
				>
					<TextControl
						label={ __( 'ID', 'axellcore-atelierclub' ) }
						value={ attributes.id }
						help={ __(
							'Matches the paired label block’s "For" field. Also used as the fallback name attribute when Name is left empty.',
							'axellcore-atelierclub'
						) }
						onChange={ ( value: string ) =>
							setAttributes( { id: value } )
						}
					/>
					<TextControl
						label={ __(
							'Name (name attribute)',
							'axellcore-atelierclub'
						) }
						value={ attributes.name }
						help={ __(
							'Leave empty to reuse the ID.',
							'axellcore-atelierclub'
						) }
						onChange={ ( value: string ) =>
							setAttributes( { name: value } )
						}
					/>
					{ attributes.type === 'hidden' && (
						<TextControl
							label={ __( 'Value', 'axellcore-atelierclub' ) }
							value={ attributes.value }
							onChange={ ( value: string ) =>
								setAttributes( { value } )
							}
						/>
					) }
					{ attributes.type !== 'hidden' && attributes.type !== 'autocomplete' && (
						<SelectControl
							label={ __( 'Autocomplete (navegador)', 'axellcore-atelierclub' ) }
							help={ __( 'Atributo autocomplete do HTML: o navegador sugere dados salvos.', 'axellcore-atelierclub' ) }
							value={ attributes.autofill }
							options={ [
								{ label: __( '— none —', 'axellcore-atelierclub' ), value: '' },
								{ label: 'name', value: 'name' },
								{ label: 'given-name', value: 'given-name' },
								{ label: 'family-name', value: 'family-name' },
								{ label: 'email', value: 'email' },
								{ label: 'tel', value: 'tel' },
								{ label: 'organization', value: 'organization' },
								{ label: 'street-address', value: 'street-address' },
								{ label: 'postal-code', value: 'postal-code' },
								{ label: 'address-level2 (cidade)', value: 'address-level2' },
								{ label: 'address-level1 (estado)', value: 'address-level1' },
								{ label: 'url', value: 'url' },
								{ label: 'off', value: 'off' },
							] as { label: string; value: string }[] }
							onChange={ ( value: string ) => setAttributes( { autofill: value } ) }
						/>
					) }
					{ attributes.type === 'autocomplete' && (
						<TextControl
							label={ __( 'Placeholder', 'axellcore-atelierclub' ) }
							value={ attributes.placeholder }
							onChange={ ( value: string ) =>
								setAttributes( { placeholder: value } )
							}
						/>
					) }
					{ attributes.type !== 'hidden' && (
							<ToggleControl
								label={ __(
									'Required',
									'axellcore-atelierclub'
								) }
								checked={ !! attributes.required }
								onChange={ ( value: boolean ) =>
									setAttributes( { required: value } )
								}
							/>
						) }
					{ attributes.type === 'autocomplete' && (
						<>
							<SelectControl
								label={ __( 'Source post type', 'axellcore-atelierclub' ) }
								value={ attributes.sourcePostType }
								options={ [
									{ label: __( 'Choose…', 'axellcore-atelierclub' ), value: '' },
									{ label: __( 'Revendas', 'axellcore-atelierclub' ), value: 'revendas' },
								] as { label: string; value: string }[] }
								onChange={ ( value: string ) =>
									setAttributes( { sourcePostType: value } )
								}
							/>
							<TextControl
								label={ __( 'Label template', 'axellcore-atelierclub' ) }
								help={ __( 'Tokens: [post_title], [tax:cidades], [tax:estados:uf], [meta:key]', 'axellcore-atelierclub' ) }
								value={ attributes.labelTemplate }
								onChange={ ( value: string ) =>
									setAttributes( { labelTemplate: value } )
								}
							/>
							<ToggleControl
								label={ __( 'Aceitar "Não encontrada"', 'axellcore-atelierclub' ) }
								help={ __( 'Mostra a opção e um campo de texto livre para quem não encontrar.', 'axellcore-atelierclub' ) }
								checked={ attributes.allowNotFound }
								onChange={ ( value: boolean ) =>
									setAttributes( { allowNotFound: value } )
								}
							/>
						</>
					) }

					{ attributes.type === 'select' && (
						<>
							<TextareaControl
								label={ __(
									'Options (one per line: Label|value)',
									'axellcore-atelierclub'
								) }
								help={ __(
									'E.g.: Individual · CPF|cpf',
									'axellcore-atelierclub'
								) }
								value={ optionsToText( attributes.options ) }
								onChange={ ( value: string ) =>
									setAttributes( {
										options: textToOptions( value ),
									} )
								}
							/>
							<TextControl
								label={ __(
									'Field (name) that populates these options',
									'axellcore-atelierclub'
								) }
								help={ __(
									'Leave empty for a static list. If set, this select starts empty/disabled and assets/js/frontend.js fetches its options from the REST cities endpoint whenever that field changes.',
									'axellcore-atelierclub'
								) }
								value={ attributes.citiesSourceName }
								onChange={ ( value: string ) =>
									setAttributes( { citiesSourceName: value } )
								}
							/>
						</>
					) }
				</PanelBody>
				<PanelBody
					title={ __(
						'Input mask (advanced)',
						'axellcore-atelierclub'
					) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Mask', 'axellcore-atelierclub' ) }
						value={ attributes.mask }
						options={ MASK_OPTIONS }
						onChange={ ( value: string ) =>
							setAttributes( { mask: value } )
						}
					/>
					{ attributes.mask === 'cpf-cnpj' && (
						<TextControl
							label={ __(
								'Field (name) that decides CPF vs. CNPJ',
								'axellcore-atelierclub'
							) }
							help={ __(
								'Name of the select field whose value ("cpf"/"cnpj") drives this mask.',
								'axellcore-atelierclub'
							) }
							value={ attributes.maskSourceName }
							onChange={ ( value: string ) =>
								setAttributes( { maskSourceName: value } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			{ ControlElement( attributes, false, setAttributes, blockProps ) }
		</>
	);
}
