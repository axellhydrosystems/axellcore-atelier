import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { FormCheckAttributes } from './types';

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FormCheckAttributes > ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Checkbox', 'axellcore-atelier' ) } initialOpen>
					<TextControl
						label={ __( 'ID', 'axellcore-atelier' ) }
						value={ attributes.id }
						onChange={ ( value: string ) => setAttributes( { id: value } ) }
					/>
					<TextControl
						label={ __( 'Name', 'axellcore-atelier' ) }
						help={ __( 'Empty uses the ID.', 'axellcore-atelier' ) }
						value={ attributes.name }
						onChange={ ( value: string ) => setAttributes( { name: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelier' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
					<ToggleControl
						label={ __( 'Checked by default', 'axellcore-atelier' ) }
						checked={ !! attributes.checked }
						onChange={ ( value: boolean ) => setAttributes( { checked: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<input type="checkbox" readOnly checked={ !! attributes.checked } />
				<RichText
					tagName="label"
					value={ attributes.text }
					onChange={ ( value: string ) => setAttributes( { text: value } ) }
					placeholder={ __( 'Consent text…', 'axellcore-atelier' ) }
					allowedFormats={ [ 'core/link', 'core/bold', 'core/italic' ] }
				/>
			</div>
		</>
	);
}
