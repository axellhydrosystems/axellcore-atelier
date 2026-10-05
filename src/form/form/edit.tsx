import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { FormAttributes } from './types';

const ALLOWED_BLOCKS = [
	'core/heading',
	'core/paragraph',
	'core/group',
	'core/columns',
	'core/column',
	'core/list',
	'core/list-item',
	'core/buttons',
	'core/button',
	'axell/form-fieldset',
	'axell/form-submission-notification',
];

type Template = readonly [
	string,
	Readonly< Record< string, unknown > >?,
	TemplateArray?,
];
type TemplateArray = ReadonlyArray< Template >;

// A fresh insert defaults to a plain group wrapping one label+control pair
// — not a fieldset (that's an explicit, separate choice; see the two
// "Fieldset"/"Legend" core/group variations registered in
// group-variations.ts).
const TEMPLATE: TemplateArray = [
	[
		'core/group',
		{ layout: { type: 'constrained' } },
		[
			[ 'axell/form-label', {} ],
			[ 'axell/form-control', {} ],
		],
	],
];

function formProps(
	attributes: FormAttributes,
	extra: Record< string, unknown >
) {
	return {
		...( attributes.submitsToRest
			? { 'data-aac-club-form': '', noValidate: true }
			: {} ),
		...extra,
	};
}

/**
 * axell/form — <form> wrapper block. Clean/generic by default
 * (`<form class="wp-block-axell-form">`, nothing else baked in) — the
 * REST-submission wiring (`data-aac-club-form`/`noValidate`, used by
 * assets/js/frontend.js) is opt-in via the `submitsToRest` attribute, and
 * the branded look (`aac-apply-form`) comes from the block's own
 * `customClassName` support, same as any other block's "Additional CSS
 * class(es)" field — not hardcoded. This plugin's own content
 * (bin/generate-content.py) sets both explicitly; a fresh, generic insert
 * of this block elsewhere doesn't carry either.
 * @param root0
 * @param root0.attributes
 */
export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FormAttributes > ) {
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps(
		formProps( attributes, blockProps ),
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			templateLock: false,
		}
	);
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Envio', 'axellcore-atelierclub' ) }>
					<SelectControl
						label={ __( 'Destino do envio', 'axellcore-atelierclub' ) }
						help={ __( 'Post: envio HTML para a própria página, sem JavaScript e sem criar registro. Membro: envio via REST, cria um cadastro de membro.', 'axellcore-atelierclub' ) }
						value={ attributes.submitsToRest ? 'member' : 'post' }
						options={ [
							{ label: __( 'Post (HTML)', 'axellcore-atelierclub' ), value: 'post' },
							{ label: __( 'Membro (REST)', 'axellcore-atelierclub' ), value: 'member' },
						] }
						onChange={ ( value: string ) =>
							setAttributes( { submitsToRest: value === 'member' } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<form { ...innerBlocksProps } />
		</>
	);
}
