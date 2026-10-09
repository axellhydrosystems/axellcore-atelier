import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import { autocompleteMarkup } from '../form-control/autocomplete-markup';
import { RESELLER_POST_TYPE, RESELLER_TEMPLATE } from './constants';
import type { ResellerAttributes } from './save';

export default function Edit( { attributes, setAttributes }: BlockEditProps< ResellerAttributes > ) {
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
						help={ __( 'Empty uses the ID.', 'axellcore-atelier' ) }
						value={ attributes.name }
						onChange={ ( value: string ) => setAttributes( { name: value } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'axellcore-atelier' ) }
						value={ attributes.placeholder }
						onChange={ ( value: string ) => setAttributes( { placeholder: value } ) }
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelier' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) => setAttributes( { required: value } ) }
					/>
					<ToggleControl
						label={ __( 'Allow "Add not found"', 'axellcore-atelier' ) }
						help={ __( 'Shows the option and the Name, State and City fields to register a new reseller.', 'axellcore-atelier' ) }
						checked={ !! attributes.allowNotFound }
						onChange={ ( value: boolean ) => setAttributes( { allowNotFound: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			{ autocompleteMarkup( {
				blockProps,
				isSave: false,
				id: attributes.id || undefined,
				name: attributes.name || attributes.id,
				placeholder: attributes.placeholder || undefined,
				postType: RESELLER_POST_TYPE,
				template: RESELLER_TEMPLATE,
				allowNotFound: !! attributes.allowNotFound,
			} ) }
		</>
	);
}
