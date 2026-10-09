import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { FormInputAttributes, FormInputOption } from './types';
import { FieldControl, FieldWrapper } from './field-element';

const TYPE_OPTIONS = [
	{ label: __( 'Text', 'axellcore-atelier' ), value: 'text' },
	{ label: __( 'Email', 'axellcore-atelier' ), value: 'email' },
	{ label: __( 'URL', 'axellcore-atelier' ), value: 'url' },
	{ label: __( 'Number', 'axellcore-atelier' ), value: 'number' },
	{ label: __( 'Phone', 'axellcore-atelier' ), value: 'tel' },
	{ label: __( 'Textarea', 'axellcore-atelier' ), value: 'textarea' },
	{
		label: __( 'Select (options)', 'axellcore-atelier' ),
		value: 'select',
	},
	{ label: __( 'Checkbox', 'axellcore-atelier' ), value: 'checkbox' },
	{ label: __( 'Hidden', 'axellcore-atelier' ), value: 'hidden' },
];

const MASK_OPTIONS = [
	{ label: __( '— none —', 'axellcore-atelier' ), value: '' },
	{ label: __( 'CPF / CNPJ', 'axellcore-atelier' ), value: 'cpf-cnpj' },
	{ label: __( 'CEP (postal code)', 'axellcore-atelier' ), value: 'cep' },
	{ label: __( 'Phone', 'axellcore-atelier' ), value: 'phone' },
];

function optionsToText( options: FormInputOption[] ): string {
	return ( options || [] )
		.map( ( o ) => `${ o.label || '' }|${ o.value || '' }` )
		.join( '\n' );
}

function textToOptions( text: string ): FormInputOption[] {
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

/**
 * axellcore/form-input — DEPRECATED, kept registered only (hidden from the
 * inserter via block.json's `"inserter": false`) so any pre-existing
 * content that used it keeps rendering/editing correctly. Superseded by
 * axell/form-label + axell/form-control (see this plugin's CLAUDE.md).
 * This is a mechanical JS→TS/build conversion only — behavior is
 * unchanged from the legacy plain-JS version.
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 */
export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FormInputAttributes > ) {
	const blockProps = useBlockProps();

	const labelEl = (
		<RichText
			tagName="span"
			className="aa-field-label-text"
			value={ attributes.label }
			onChange={ ( v: string ) => setAttributes( { label: v } ) }
			aria-label={
				attributes.label
					? __( 'Label', 'axellcore-atelier' )
					: __( 'Empty label', 'axellcore-atelier' )
			}
			data-empty={ ! attributes.label }
			placeholder={ __( 'Field label…', 'axellcore-atelier' ) }
			allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
		/>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Field', 'axellcore-atelier' ) }
					initialOpen
				>
					<SelectControl
						label={ __( 'Type', 'axellcore-atelier' ) }
						value={ attributes.type }
						options={ TYPE_OPTIONS }
						onChange={ ( v: string ) =>
							setAttributes( { type: v } )
						}
					/>
					{ 'checkbox' === attributes.type && (
						<SelectControl
							label={ __( 'Variant', 'axellcore-atelier' ) }
							value={ attributes.variant as 'field' | 'consent' }
							options={ [
								{
									label: __(
										'Default',
										'axellcore-atelier'
									),
									value: 'field',
								},
								{
									label: __(
										'Consent (LGPD)',
										'axellcore-atelier'
									),
									value: 'consent',
								},
							] }
							onChange={ ( v: string ) =>
								setAttributes( { variant: v } )
							}
						/>
					) }
					{ 'select' !== attributes.type &&
						'checkbox' !== attributes.type &&
						'hidden' !== attributes.type && (
							<TextControl
								label={ __(
									'Placeholder',
									'axellcore-atelier'
								) }
								value={ attributes.placeholder }
								onChange={ ( v: string ) =>
									setAttributes( { placeholder: v } )
								}
							/>
						) }
					{ 'hidden' === attributes.type && (
						<TextControl
							label={ __( 'Value', 'axellcore-atelier' ) }
							value={ attributes.value }
							onChange={ ( v: string ) =>
								setAttributes( { value: v } )
							}
						/>
					) }
					{ 'checkbox' === attributes.type && (
						<ToggleControl
							label={ __(
								'Checked by default',
								'axellcore-atelier'
							) }
							checked={ !! attributes.checked }
							onChange={ ( v: boolean ) =>
								setAttributes( { checked: v } )
							}
						/>
					) }
					{ 'hidden' !== attributes.type &&
						'checkbox' !== attributes.type && (
							<ToggleControl
								label={ __(
									'Required',
									'axellcore-atelier'
								) }
								checked={ !! attributes.required }
								onChange={ ( v: boolean ) =>
									setAttributes( { required: v } )
								}
							/>
						) }
					{ 'field' === attributes.variant &&
						'hidden' !== attributes.type && (
							<TextControl
								label={ __(
									'Hint text (optional)',
									'axellcore-atelier'
								) }
								value={ attributes.hint }
								onChange={ ( v: string ) =>
									setAttributes( { hint: v } )
								}
							/>
						) }
					{ 'select' === attributes.type && (
						<TextareaControl
							label={ __(
								'Options (one per line: Label|value)',
								'axellcore-atelier'
							) }
							help={ __(
								'E.g.: Individual · CPF|cpf',
								'axellcore-atelier'
							) }
							value={ optionsToText( attributes.options ) }
							onChange={ ( v: string ) =>
								setAttributes( { options: textToOptions( v ) } )
							}
						/>
					) }
					{ 'select' === attributes.type && (
						<TextControl
							label={ __(
								'Field (name) that populates these options',
								'axellcore-atelier'
							) }
							help={ __(
								'Leave empty for a static list. If set, this select starts empty/disabled and assets/js/frontend.js fetches its options from the REST cities endpoint whenever that field changes.',
								'axellcore-atelier'
							) }
							value={ attributes.citiesSourceName }
							onChange={ ( v: string ) =>
								setAttributes( { citiesSourceName: v } )
							}
						/>
					) }
				</PanelBody>
				<PanelBody
					title={ __(
						'Input mask (advanced)',
						'axellcore-atelier'
					) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Mask', 'axellcore-atelier' ) }
						value={ attributes.mask }
						options={ MASK_OPTIONS }
						onChange={ ( v: string ) =>
							setAttributes( { mask: v } )
						}
					/>
					{ 'cpf-cnpj' === attributes.mask && (
						<TextControl
							label={ __(
								'Field (name) that decides CPF vs. CNPJ',
								'axellcore-atelier'
							) }
							help={ __(
								'Name of the select field whose value ("cpf"/"cnpj") drives this mask.',
								'axellcore-atelier'
							) }
							value={ attributes.maskSourceName }
							onChange={ ( v: string ) =>
								setAttributes( { maskSourceName: v } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			<InspectorControls group="advanced">
				<TextControl
					label={ __(
						'Field name (name attribute)',
						'axellcore-atelier'
					) }
					value={ attributes.name }
					help={ __(
						'Leave empty to derive it from the label automatically.',
						'axellcore-atelier'
					) }
					onChange={ ( v: string ) => setAttributes( { name: v } ) }
				/>
			</InspectorControls>
			{ FieldWrapper(
				attributes,
				labelEl,
				FieldControl( attributes, false, setAttributes ),
				blockProps,
				false
			) }
		</>
	);
}
