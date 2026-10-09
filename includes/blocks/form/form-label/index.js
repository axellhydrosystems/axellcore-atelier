/**
 * axell/form-label — a form field's label. Static (no PHP render), no
 * build step (plain browser JS against wp.* globals). Independent sibling
 * of axell/form-control (paired via matching `for`/`id`, the more correct
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
 *
 * The old "consent" variant (checkbox-first, label-with-embedded-link
 * after, boxed row) is no longer a special case here either — it's just
 * ordinary composition: a core/group (className aa-consent, reusing
 * assets/css/sections.css's existing rule verbatim) containing an
 * axell/form-control (checkbox) *then* this block.
 *
 * Source strings are English; translations live in languages/*.po (see
 * bin/release.sh + `wp i18n make-pot`).
 */
( function ( blocks, element, blockEditor, components, richText, i18n ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var __ = i18n.__;

	function classesFor( attributes, wrapperProps ) {
		var classes = [];
		if ( wrapperProps && wrapperProps.className ) {
			classes.push( wrapperProps.className );
		}
		if ( attributes.visuallyHidden ) {
			classes.push( 'is-visually-hidden' );
		}
		return classes.join( ' ' );
	}

	blocks.registerBlockType( 'axell/form-label', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: classesFor( attributes ) } );

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Label', 'axellcore-atelier' ), initialOpen: true },
						el( ToggleControl, {
							label: __( 'Visually hidden', 'axellcore-atelier' ),
							help: __( 'Keeps the label readable by screen readers while hiding it visually — useful when the field’s purpose is already clear from context (e.g. a placeholder-only search box).', 'axellcore-atelier' ),
							checked: !! attributes.visuallyHidden,
							onChange: function ( v ) {
								setAttributes( { visuallyHidden: v } );
							},
						} )
					)
				),
				el(
					InspectorControls,
					{ group: 'advanced' },
					el( TextControl, {
						label: __( 'For', 'axellcore-atelier' ),
						value: attributes.for,
						help: __( 'The id of the axell/form-control this label belongs to.', 'axellcore-atelier' ),
						onChange: function ( v ) {
							setAttributes( { for: v } );
						},
					} )
				),
				el( RichText, Object.assign( {}, blockProps, {
					tagName: 'label',
					htmlFor: attributes.for || undefined,
					value: attributes.text,
					onChange: function ( v ) {
						setAttributes( { text: v } );
					},
					'aria-label': attributes.text ? __( 'Label', 'axellcore-atelier' ) : __( 'Empty label', 'axellcore-atelier' ),
					'data-empty': ! attributes.text,
					placeholder: __( 'Field label…', 'axellcore-atelier' ),
					allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
				} ) )
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save( { className: classesFor( attributes ) } );
			return el( RichText.Content, Object.assign( {}, blockProps, {
				tagName: 'label',
				htmlFor: attributes.for || undefined,
				value: attributes.text,
			} ) );
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.richText, window.wp.i18n );
