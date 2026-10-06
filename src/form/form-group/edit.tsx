import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	RangeControl,
	__experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import {
	WIDTHS,
	WIDTH_UNITS,
	widthStyle,
	withDefaultUnit,
} from './width';

const ALLOWED_BLOCKS = [
	'axell/form-label',
	'axell/form-control',
	'axell/form-select',
	'axell/form-text',
	'axell/form-control-country',
	'axell/form-control-state',
	'axell/form-control-city',
	'axell/form-control-postal',
	'axell/form-control-phone',
	'axell/form-control-reseller',
	'axell/form-control-br-revenue-id',
	'axell/form-control-br-revenue-id-person',
	'axell/form-control-br-revenue-id-legal',
];

const TEMPLATE: Array< [ string, Record< string, unknown > ] > = [
	[ 'axell/form-label', {} ],
	[ 'axell/form-control', {} ],
];

/**
 * Icon of the custom-value toggle (the sliders glyph of the core dimensions
 * presets).
 */
const customIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		fill="currentColor"
		width="24"
		height="24"
		aria-hidden="true"
		focusable="false"
	>
		<path d="m19 7.5h-7.628c-.3089-.87389-1.1423-1.5-2.122-1.5-.97966 0-1.81309.62611-2.12197 1.5h-2.12803v1.5h2.12803c.30888.87389 1.14231 1.5 2.12197 1.5.9797 0 1.8131-.62611 2.122-1.5h7.628z" />
		<path d="m19 15h-2.128c-.3089-.8739-1.1423-1.5-2.122-1.5s-1.8131.6261-2.122 1.5h-7.628v1.5h7.628c.3089.8739 1.1423 1.5 2.122 1.5s1.8131-.6261 2.122-1.5h2.128z" />
	</svg>
);

/**
 * axell/form-group — one form field: a label, a form control (or select) and
 * an optional axell/form-text. Only those blocks can be inside it. The
 * "Campos" panel in the Settings sidebar sets its width: a slider of twelfths,
 * or, with the toggle, a value and unit typed in.
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 */
export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< { fieldWidth: string } > ) {
	const blockProps = useBlockProps( {
		style: widthStyle( attributes.fieldWidth ),
	} );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
		template: TEMPLATE,
		templateInsertUpdatesSelection: false,
	} );

	const isPreset = WIDTHS.includes( attributes.fieldWidth );
	const [ isCustom, setIsCustom ] = useState< boolean >(
		attributes.fieldWidth !== '' && ! isPreset
	);
	const sliderValue =
		WIDTHS.indexOf( attributes.fieldWidth ) + 1 || WIDTHS.length;

	function toggleCustom() {
		if ( isCustom && ! isPreset ) {
			setAttributes( { fieldWidth: '' } );
		}
		setIsCustom( ! isCustom );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'axellcore-atelierclub' ) } initialOpen>
					<span
						className="components-base-control__label"
						style={ {
							display: 'block',
							fontWeight: 600,
							textTransform: 'uppercase',
						} }
					>
						{ __( 'Largura', 'axellcore-atelierclub' ) }
					</span>
					<div
						style={ {
							display: 'flex',
							alignItems: 'center',
							gap: '8px',
						} }
					>
						<div style={ { flex: 1, minWidth: 0 } }>
							{ isCustom ? (
								<UnitControl
									value={ attributes.fieldWidth || undefined }
									units={ WIDTH_UNITS }
									onChange={ ( value?: string ) =>
										setAttributes( { fieldWidth: withDefaultUnit( value ) } )
									}
								/>
							) : (
								<RangeControl
									min={ 1 }
									max={ WIDTHS.length }
									step={ 1 }
									marks={ true }
									value={ sliderValue }
									withInputField={ false }
									onChange={ ( value?: number ) =>
										setAttributes( { fieldWidth: WIDTHS[ ( value ?? 1 ) - 1 ] } )
									}
								/>
							) }
						</div>
						<Button
							className="preset-input-control__custom-toggle"
							size="small"
							icon={ customIcon }
							label={ __( 'Definir valor personalizado', 'axellcore-atelierclub' ) }
							aria-pressed={ isCustom }
							onClick={ toggleCustom }
						/>
					</div>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}
