/**
 * axell/form-control — the actual input/select/textarea of a form field.
 * Static (no PHP render), no build step (plain browser JS against wp.*
 * globals). Successor to axellcore/form-input's `FieldControl()`, minus
 * label/hint/variant handling (label moves to axell/form-label — paired via
 * matching `id`/`for`; hint becomes a plain core/paragraph sibling; the old
 * "consent" variant is now just ordinary block composition — see this
 * plugin's CLAUDE.md for the full reasoning).
 *
 * Unlike axellcore/form-input (a wrapping `<div class="aa-field">` around
 * a `<label>` and the control), this block's OWN root element IS the
 * control itself — `useBlockProps()`/`useBlockProps.save()` applied
 * directly onto the `<input>`/`<select>`/`<textarea>`, so the block's
 * native color/typography/border/spacing supports apply to the visible
 * control with zero extra plumbing (unlike the reference block's own
 * save.js, which has to manually merge `getColorClassesAndStyles()`/
 * `getBorderClassesAndStyles()` onto an inner element because ITS root is
 * a separate wrapping div — not needed here since there's no wrapper).
 *
 * Source strings are English; translations live in languages/*.po (see
 * bin/release.sh + `wp i18n make-pot`).
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;
	var __ = i18n.__;

	// Same icon as axell/form-input used to carry (ported from Gutenberg
	// 23.9.1's core/form-input, packages/block-library/src/form-input/
	// icons.js — copied verbatim; see this plugin's CLAUDE.md).
	var ICON = el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', role: 'img', 'aria-hidden': 'true', focusable: 'false' },
		el( 'path', {
			d: 'M5.547 18.892A.99.99 0 0 0 6 19h.72v1H6a1.99 1.99 0 0 1-.908-.22l.455-.888ZM9.12 20H7.68v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.587-.22c-.272.14-.58.22-.907.22h-.72v-1H18a.99.99 0 0 0 .453-.108l.454.888ZM5.108 17.547a.99.99 0 0 0 0 .906l-.89.454a1.99 1.99 0 0 1 0-1.815l.89.455Zm14.672-.455a1.99 1.99 0 0 1 0 1.815l-.888-.454a.99.99 0 0 0 0-.906l.888-.455ZM6.72 17H6a.99.99 0 0 0-.453.108l-.455-.89A1.99 1.99 0 0 1 6 16h.72v1ZM18 16c.327 0 .635.08.907.219l-.454.89A.99.99 0 0 0 18 17h-.72v-1H18Zm-8.88 1H7.68v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1ZM5.5 14.28H4.25v-1H5.5v1Zm2.5 0H6.5v-1H8v1Zm2.5 0H9v-1h1.5v1Zm2.25 0H11.5v-1h1.25v1ZM18 7a2 2 0 1 1 0 4H6a2 2 0 1 1 0-4h12ZM6 8.5a.5.5 0 0 0 0 1h12a.5.5 0 0 0 0-1H6Zm7-3H4V4h9v1.5Z',
		} )
	);

	var TYPE_OPTIONS = [
		{ label: __( 'Text', 'axellcore-atelier' ), value: 'text' },
		{ label: __( 'Email', 'axellcore-atelier' ), value: 'email' },
		{ label: __( 'URL', 'axellcore-atelier' ), value: 'url' },
		{ label: __( 'Number', 'axellcore-atelier' ), value: 'number' },
		{ label: __( 'Phone', 'axellcore-atelier' ), value: 'tel' },
		{ label: __( 'Textarea', 'axellcore-atelier' ), value: 'textarea' },
		{ label: __( 'Select (options)', 'axellcore-atelier' ), value: 'select' },
		{ label: __( 'Checkbox', 'axellcore-atelier' ), value: 'checkbox' },
		{ label: __( 'Hidden', 'axellcore-atelier' ), value: 'hidden' },
	];

	var MASK_OPTIONS = [
		{ label: __( '— none —', 'axellcore-atelier' ), value: '' },
		{ label: __( 'CPF / CNPJ', 'axellcore-atelier' ), value: 'cpf-cnpj' },
		{ label: __( 'CEP (postal code)', 'axellcore-atelier' ), value: 'cep' },
		{ label: __( 'Phone', 'axellcore-atelier' ), value: 'phone' },
	];

	function optionsToText( options ) {
		return ( options || [] )
			.map( function ( o ) {
				return ( o.label || '' ) + '|' + ( o.value || '' );
			} )
			.join( '\n' );
	}

	function textToOptions( text ) {
		return text
			.split( '\n' )
			.map( function ( line ) {
				return line.trim();
			} )
			.filter( Boolean )
			.map( function ( line ) {
				var parts = line.split( '|' );
				return {
					label: ( parts[ 0 ] || '' ).trim(),
					value: ( parts[ 1 ] !== undefined ? parts[ 1 ] : parts[ 0 ] || '' ).trim(),
				};
			} );
	}

	/**
	 * Editor-only placeholder shown in place of the (otherwise invisible,
	 * unselectable) hidden `<input>` — ported from the reference block's
	 * `.is-input-hidden` treatment. Inline styles, not a stylesheet rule —
	 * self-contained regardless of which page this block ends up on.
	 */
	function HiddenFieldPlaceholder() {
		return el(
			'span',
			{
				className: 'aa-field-hidden-placeholder',
				style: {
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'center',
					boxSizing: 'border-box',
					width: '100%',
					padding: '0.6em',
					fontSize: '0.85em',
					opacity: 0.6,
					border: '1px dashed currentColor',
				},
			},
			__( 'Hidden field', 'axellcore-atelier' )
		);
	}

	/**
	 * Build the control element, shared between edit() and save(). `name`
	 * falls back to `id` when left blank (no `label` attribute lives on this
	 * block anymore to derive a slug from — that fallback is
	 * axell/form-label's own concern if it ever needs one).
	 */
	function ControlElement( attributes, isSave, setAttributes, blockProps ) {
		var type = attributes.type;
		var id = attributes.id || undefined;
		var name = attributes.name || attributes.id || undefined;
		var required = attributes.required;
		var placeholder = attributes.placeholder;

		var common = Object.assign( {}, blockProps, {
			id: id,
			name: name,
			required: required || undefined,
			'aria-required': required || undefined,
		} );

		if ( isSave ) {
			common.placeholder = placeholder || undefined;
			if ( attributes.mask ) {
				common[ 'data-aa-mask' ] = attributes.mask;
			}
			if ( attributes.maskSourceName ) {
				common[ 'data-aa-mask-source' ] = attributes.maskSourceName;
			}
			if ( attributes.citiesSourceName ) {
				common[ 'data-aa-cities-source' ] = attributes.citiesSourceName;
				// Starts empty (populated by frontend.js once the source
				// field has a value) — disabled until then, same as the
				// real page markup this generates.
				common.disabled = true;
			}
		}

		var editableTextProps = ! isSave && {
			'aria-label': __( 'Optional placeholder text', 'axellcore-atelier' ),
			placeholder: placeholder ? undefined : __( 'Optional placeholder…', 'axellcore-atelier' ),
			value: placeholder,
			onChange: function ( event ) {
				setAttributes( { placeholder: event.target.value } );
			},
		};

		if ( 'hidden' === type ) {
			if ( ! isSave ) {
				return el( 'div', blockProps, HiddenFieldPlaceholder() );
			}
			return el( 'input', { type: 'hidden', name: name, value: attributes.value || '' } );
		}

		if ( 'textarea' === type ) {
			return el( 'textarea', Object.assign( {}, common, editableTextProps || {} ) );
		}

		if ( 'select' === type ) {
			var options = attributes.options || [];
			return el(
				'select',
				common,
				el( 'option', { value: '' }, placeholder || __( 'Select an option', 'axellcore-atelier' ) ),
				options.map( function ( o, i ) {
					return el( 'option', { key: i, value: o.value }, o.label );
				} )
			);
		}

		if ( 'checkbox' === type ) {
			return el(
				'input',
				Object.assign( {}, common, {
					type: 'checkbox',
					checked: isSave ? undefined : !! attributes.checked,
					defaultChecked: isSave ? !! attributes.checked : undefined,
				} )
			);
		}

		return el( 'input', Object.assign( {}, common, editableTextProps || {}, { type: type } ) );
	}

	blocks.registerBlockType( 'axell/form-control', {
		icon: ICON,
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Control', 'axellcore-atelier' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Type', 'axellcore-atelier' ),
							value: attributes.type,
							options: TYPE_OPTIONS,
							onChange: function ( v ) {
								setAttributes( { type: v } );
							},
						} ),
						'hidden' === attributes.type &&
							el( TextControl, {
								label: __( 'Value', 'axellcore-atelier' ),
								value: attributes.value,
								onChange: function ( v ) {
									setAttributes( { value: v } );
								},
							} ),
						'checkbox' === attributes.type &&
							el( ToggleControl, {
								label: __( 'Checked by default', 'axellcore-atelier' ),
								checked: !! attributes.checked,
								onChange: function ( v ) {
									setAttributes( { checked: v } );
								},
							} ),
						'hidden' !== attributes.type &&
							'checkbox' !== attributes.type &&
							el( ToggleControl, {
								label: __( 'Required', 'axellcore-atelier' ),
								checked: !! attributes.required,
								onChange: function ( v ) {
									setAttributes( { required: v } );
								},
							} ),
						'select' === attributes.type &&
							el( TextareaControl, {
								label: __( 'Options (one per line: Label|value)', 'axellcore-atelier' ),
								help: __( 'E.g.: Individual · CPF|cpf', 'axellcore-atelier' ),
								value: optionsToText( attributes.options ),
								onChange: function ( v ) {
									setAttributes( { options: textToOptions( v ) } );
								},
							} ),
						'select' === attributes.type &&
							el( TextControl, {
								label: __( 'Field (name) that populates these options', 'axellcore-atelier' ),
								help: __( 'Leave empty for a static list. If set, this select starts empty/disabled and assets/js/frontend.js fetches its options from the REST cities endpoint whenever that field changes.', 'axellcore-atelier' ),
								value: attributes.citiesSourceName,
								onChange: function ( v ) {
									setAttributes( { citiesSourceName: v } );
								},
							} )
					),
					el(
						PanelBody,
						{ title: __( 'Input mask (advanced)', 'axellcore-atelier' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Mask', 'axellcore-atelier' ),
							value: attributes.mask,
							options: MASK_OPTIONS,
							onChange: function ( v ) {
								setAttributes( { mask: v } );
							},
						} ),
						'cpf-cnpj' === attributes.mask &&
							el( TextControl, {
								label: __( 'Field (name) that decides CPF vs. CNPJ', 'axellcore-atelier' ),
								help: __( 'Name of the select field whose value ("cpf"/"cnpj") drives this mask.', 'axellcore-atelier' ),
								value: attributes.maskSourceName,
								onChange: function ( v ) {
									setAttributes( { maskSourceName: v } );
								},
							} )
					)
				),
				el(
					InspectorControls,
					{ group: 'advanced' },
					el( TextControl, {
						label: __( 'ID', 'axellcore-atelier' ),
						value: attributes.id,
						help: __( 'Matches the paired form-label block’s "For" field. Also used as the fallback name attribute when Name is left empty.', 'axellcore-atelier' ),
						onChange: function ( v ) {
							setAttributes( { id: v } );
						},
					} ),
					el( TextControl, {
						label: __( 'Name (name attribute)', 'axellcore-atelier' ),
						value: attributes.name,
						help: __( 'Leave empty to reuse the ID.', 'axellcore-atelier' ),
						onChange: function ( v ) {
							setAttributes( { name: v } );
						},
					} )
				),
				ControlElement( attributes, false, setAttributes, blockProps )
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save();
			return ControlElement( attributes, true, null, blockProps );
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
