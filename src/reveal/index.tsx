import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ComponentType } from '@wordpress/element';

/**
 * "Reveal on scroll" as a block support. Adds one attribute and one Inspector
 * panel to a fixed list of core blocks. Nothing is written to the saved HTML;
 * render.php (includes/class-reveal.php) adds the markup on the front end.
 */

export type RevealMode = 'none' | 'block' | 'items';

const REVEAL_BLOCKS = [
	'core/group',
	'core/columns',
	'core/column',
	'core/list',
	'core/heading',
	'core/paragraph',
	'core/image',
	'core/buttons',
];

const REVEAL_OPTIONS: { label: string; value: RevealMode }[] = [
	{ label: __( 'None', 'axellcore-atelier' ), value: 'none' },
	{
		label: __( 'Whole block', 'axellcore-atelier' ),
		value: 'block',
	},
	{
		label: __( 'Each child', 'axellcore-atelier' ),
		value: 'items',
	},
];

interface RevealAttributes {
	revealMode?: RevealMode;
}

addFilter(
	'blocks.registerBlockType',
	'axellcore-atelier/reveal-attribute',
	( settings: Record< string, unknown >, name: string ) => {
		if ( ! REVEAL_BLOCKS.includes( name ) ) {
			return settings;
		}
		const attributes = ( settings.attributes ?? {} ) as Record<
			string,
			unknown
		>;
		return {
			...settings,
			attributes: {
				...attributes,
				revealMode: {
					type: 'string',
					enum: REVEAL_OPTIONS.map( ( option ) => option.value ),
					default: 'none',
				},
			},
		};
	}
);

const withRevealControl = createHigherOrderComponent(
	( BlockEdit: ComponentType< any > ) =>
		function WithRevealControl( props: any ) {
			if ( ! REVEAL_BLOCKS.includes( props.name ) ) {
				return <BlockEdit { ...props } />;
			}

			const attributes = props.attributes as RevealAttributes;
			const setAttributes = props.setAttributes as (
				next: RevealAttributes
			) => void;

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<PanelBody
							title={ __(
								'Reveal on scroll',
								'axellcore-atelier'
							) }
							initialOpen={ false }
						>
							<SelectControl
								label={ __(
									'Animate on scroll',
									'axellcore-atelier'
								) }
								value={ attributes.revealMode ?? 'none' }
								options={ REVEAL_OPTIONS }
								onChange={ ( revealMode: string ) =>
									setAttributes( {
										revealMode: revealMode as RevealMode,
									} )
								}
								help={ __(
									'Whole block fades in as one. Each child fades in on its own, for card grids and lists.',
									'axellcore-atelier'
								) }
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							/>
						</PanelBody>
					</InspectorControls>
				</>
			);
		},
	'withRevealControl'
);

addFilter(
	'editor.BlockEdit',
	'axellcore-atelier/reveal-control',
	withRevealControl
);
