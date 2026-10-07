import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	BlockControls,
	RichText,
} from '@wordpress/block-editor';
import { ToolbarButton } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { FormFieldsetAttributes } from './types';

const ALLOWED_BLOCKS = [
	'axell/form-group',
	'core/paragraph',
	'core/heading',
	'core/group',
	'core/columns',
	'core/html',
];

/**
 * axell/fieldset — groups a set of related form fields inside a real
 * `<fieldset>`/`<legend>` pair. The legend is a toolbar toggle — "Add
 * legend"/"Remove legend" — mirroring core/image's own "Add caption"/
 * "Remove caption" pattern exactly (local `showLegend` state, clearing the
 * `legend` attribute on removal). This is also *why* this stays a real
 * custom block rather than a `core/group` variation with
 * `tagName:"fieldset"` (see the two "Fieldset (plain)"/"Legend (plain)"
 * variations registered alongside axell/form for that simpler, no-legend-
 * concept alternative) — variations can't add stateful toolbar behavior,
 * only preset attributes/templates.
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 */
export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FormFieldsetAttributes > ) {
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED_BLOCKS,
	} );

	const [ showLegend, setShowLegend ] = useState< boolean >(
		!! attributes.legend
	);

	function toggleLegend() {
		if ( showLegend ) {
			setAttributes( { legend: '' } );
		}
		setShowLegend( ! showLegend );
	}

	return (
		<>
			<BlockControls group="block">
				<ToolbarButton
					icon="edit"
					label={
						showLegend
							? __( 'Remove legend', 'axellcore-atelierclub' )
							: __( 'Add legend', 'axellcore-atelierclub' )
					}
					isPressed={ showLegend }
					onClick={ toggleLegend }
				/>
			</BlockControls>
			<fieldset { ...innerBlocksProps }>
				{ showLegend && (
					<RichText
						tagName="legend"
						value={ attributes.legend }
						onChange={ ( value: string ) =>
							setAttributes( { legend: value } )
						}
						placeholder={ __( 'Legend…', 'axellcore-atelierclub' ) }
						allowedFormats={ [] }
					/>
				) }
				{ innerBlocksProps.children as React.ReactNode }
			</fieldset>
		</>
	);
}
