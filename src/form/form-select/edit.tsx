import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { FormSelectAttributes, FormSelectOption } from './types';
import SelectElement from './element';

function optionsToText( options: FormSelectOption[] ): string {
	return ( options || [] )
		.map( ( o ) => `${ o.label || '' }|${ o.value || '' }` )
		.join( '\n' );
}

function textToOptions( text: string ): FormSelectOption[] {
	return text
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.map( ( line ) => {
			const parts = line.split( '|' );
			return {
				label: ( parts[ 0 ] || '' ).trim(),
				value: ( parts[ 1 ] !== undefined ? parts[ 1 ] : parts[ 0 ] || '' ).trim(),
			};
		} );
}

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FormSelectAttributes > ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Select', 'axellcore-atelierclub' ) } initialOpen>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelierclub' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'axellcore-atelierclub' ) }
						value={ attributes.placeholder }
						onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
					/>
					<TextareaControl
						label={ __( 'Options', 'axellcore-atelierclub' ) }
						help={ __( 'Uma por linha: rótulo|valor (sem | usa o rótulo como valor).', 'axellcore-atelierclub' ) }
						value={ optionsToText( attributes.options ) }
						onChange={ ( value: string ) => setAttributes( { options: textToOptions( value ) } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ SelectElement( attributes, blockProps ) }
		</>
	);
}
