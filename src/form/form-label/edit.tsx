import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { FormLabelAttributes } from './types';

function classesFor(
	attributes: FormLabelAttributes,
	wrapperClassName?: string
): string {
	const classes = [];
	if ( wrapperClassName ) {
		classes.push( wrapperClassName );
	}
	if ( attributes.visuallyHidden ) {
		classes.push( 'is-visually-hidden' );
	}
	if ( attributes.required ) {
		classes.push( 'is-required' );
	}
	return classes.join( ' ' );
}

/**
 * axell/form-label — a form field's label. Independent sibling of
 * axell/form-control (paired via matching `for`/`id`, the more correct
 * accessibility pattern vs. the old axellcore/form-input's implicit
 * `<label>{text}{input}</label>` nesting — see this plugin's CLAUDE.md).
 *
 * The RichText's own rendered tag IS the block's root `<label>` element —
 * `useBlockProps()` merged directly onto it, same pattern `core/heading`
 * uses (its own `<h1>`-`<h6>` tag) — not a `<label>` wrapping a separate
 * `<span>`. A required-field asterisk, if wanted, is just part of the
 * label's own rich text content (the generator embeds a styled span
 * directly into `text`, exactly like the consent checkbox's embedded
 * `<a>` link already does) — not a separate attribute, since there's no
 * other child element here to hold one without a second wrapper.
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 */
export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< FormLabelAttributes > ) {
	const blockProps = useBlockProps( { className: classesFor( attributes ) } );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Label', 'axellcore-atelier' ) }
					initialOpen
				>
					<TextControl
					label={ __( 'For', 'axellcore-atelier' ) }
					value={ attributes.for }
					help={ __(
						'The id of the axell/form-control this label belongs to.',
						'axellcore-atelier'
					) }
					onChange={ ( value: string ) =>
						setAttributes( { for: value } )
					}
				/>
					<ToggleControl
						label={ __(
							'Visually hidden',
							'axellcore-atelier'
						) }
						help={ __(
							'Keeps the label readable by screen readers while hiding it visually — useful when the field’s purpose is already clear from context (e.g. a placeholder-only search box).',
							'axellcore-atelier'
						) }
						checked={ !! attributes.visuallyHidden }
						onChange={ ( value: boolean ) =>
							setAttributes( { visuallyHidden: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Required', 'axellcore-atelier' ) }
						help={ __( 'Shows that the related input is required.', 'axellcore-atelier' ) }
						checked={ !! attributes.required }
						onChange={ ( value: boolean ) =>
							setAttributes( { required: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			{ /* No `for` here: in the editor a click on the label would move the
			   focus to its control instead of editing the label text. The
			   association lives in save.tsx (the front end). */ }
			<RichText
				{ ...blockProps }
				tagName="label"
				value={ attributes.text }
				onChange={ ( value: string ) =>
					setAttributes( { text: value } )
				}
				aria-label={
					attributes.text
						? __( 'Label', 'axellcore-atelier' )
						: __( 'Empty label', 'axellcore-atelier' )
				}
				data-empty={ ! attributes.text }
				placeholder={ __( 'Field label…', 'axellcore-atelier' ) }
				allowedFormats={ [ 'core/bold', 'core/italic', 'core/link' ] }
			/>
		</>
	);
}
