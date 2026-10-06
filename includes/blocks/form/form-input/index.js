/**
 * axellcore/form-input — DEPRECATED, kept registered only (hidden from the
 * inserter via block.json's `"inserter": false`) so any pre-existing
 * content that used it keeps rendering/editing correctly. Superseded by
 * axell/form-label + axell/form-control (see this plugin's CLAUDE.md for
 * why: Gutenberg's `deprecated`/`migrate` API can't automatically split one
 * block into sibling blocks of a different name on page load — confirmed
 * by reading core/gallery's own v1→v2 migration — so this file is NOT
 * touched further; new content is generated directly as the new blocks by
 * bin/generate-content.py instead). Everything below this comment is
 * unchanged legacy behavior.
 *
 * Static (no PHP render), no build step (plain browser JS against wp.*
 * globals). Renders text/email/url/number/tel/textarea/select/checkbox/
 * hidden, matching the markup the plugin's assets/css/sections.css (ported
 * from source/index.html) expects: `.aa-field` wrapper (or
 * `.aa-consent` for the consent variant).
 *
 * Source strings are English; translations live in languages/*.po (see
 * bin/release.sh + `wp i18n make-pot`).
 *
 * Several editor-ergonomics choices here (the "Name" field living under
 * Inspector "Advanced", the label's aria-label/data-empty, the hidden-field
 * placeholder, the name-from-label fallback) are ported from Gutenberg's own
 * removed experimental core/form-input block — see this plugin's own
 * CLAUDE.md and docs/reference/gutenberg-core-form-v23.9.1/ for the full
 * heritage note and what was deliberately NOT ported.
 */
( function ( blocks, element, blockEditor, components, richText, i18n ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;
	var __ = i18n.__;

	// Same icon as the reference block (Gutenberg 23.9.1's core/form-input,
	// packages/block-library/src/form-input/icons.js — copied verbatim; see
	// axellcore/form's index.js for why this lives here instead of
	// block.json's `icon` field).
	var ICON = el(
		'svg',
		{ xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', role: 'img', 'aria-hidden': 'true', focusable: 'false' },
		el( 'path', {
			d: 'M5.547 18.892A.99.99 0 0 0 6 19h.72v1H6a1.99 1.99 0 0 1-.908-.22l.455-.888ZM9.12 20H7.68v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.587-.22c-.272.14-.58.22-.907.22h-.72v-1H18a.99.99 0 0 0 .453-.108l.454.888ZM5.108 17.547a.99.99 0 0 0 0 .906l-.89.454a1.99 1.99 0 0 1 0-1.815l.89.455Zm14.672-.455a1.99 1.99 0 0 1 0 1.815l-.888-.454a.99.99 0 0 0 0-.906l.888-.455ZM6.72 17H6a.99.99 0 0 0-.453.108l-.455-.89A1.99 1.99 0 0 1 6 16h.72v1ZM18 16c.327 0 .635.08.907.219l-.454.89A.99.99 0 0 0 18 17h-.72v-1H18Zm-8.88 1H7.68v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1Zm2.4 0h-1.44v-1h1.44v1ZM5.5 14.28H4.25v-1H5.5v1Zm2.5 0H6.5v-1H8v1Zm2.5 0H9v-1h1.5v1Zm2.25 0H11.5v-1h1.25v1ZM18 7a2 2 0 1 1 0 4H6a2 2 0 1 1 0-4h12ZM6 8.5a.5.5 0 0 0 0 1h12a.5.5 0 0 0 0-1H6Zm7-3H4V4h9v1.5Z',
		} )
	);

	var TYPE_OPTIONS = [
		{ label: __( 'Text', 'axellcore-atelierclub' ), value: 'text' },
		{ label: __( 'Email', 'axellcore-atelierclub' ), value: 'email' },
		{ label: __( 'URL', 'axellcore-atelierclub' ), value: 'url' },
		{ label: __( 'Number', 'axellcore-atelierclub' ), value: 'number' },
		{ label: __( 'Phone', 'axellcore-atelierclub' ), value: 'tel' },
		{ label: __( 'Textarea', 'axellcore-atelierclub' ), value: 'textarea' },
		{ label: __( 'Select (options)', 'axellcore-atelierclub' ), value: 'select' },
		{ label: __( 'Checkbox', 'axellcore-atelierclub' ), value: 'checkbox' },
		{ label: __( 'Hidden', 'axellcore-atelierclub' ), value: 'hidden' },
	];

	var MASK_OPTIONS = [
		{ label: __( '— none —', 'axellcore-atelierclub' ), value: '' },
		{ label: __( 'CPF / CNPJ', 'axellcore-atelierclub' ), value: 'cpf-cnpj' },
		{ label: __( 'CEP (postal code)', 'axellcore-atelierclub' ), value: 'cep' },
		{ label: __( 'Phone', 'axellcore-atelierclub' ), value: 'phone' },
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

	// Accent map mirrors the one already proven server-side in
	// includes/class-locations.php (sanitize_title()) — kept here instead of
	// adding the reference block's `remove-accents` npm dependency, since
	// this project has no JS build step.
	var ACCENT_MAP = {
		a: 'áàãâä',
		e: 'éèêë',
		i: 'íìîï',
		o: 'óòõôö',
		u: 'úùûü',
		c: 'ç',
		n: 'ñ',
	};
	var ACCENT_LOOKUP = ( function () {
		var lookup = {};
		Object.keys( ACCENT_MAP ).forEach( function ( plain ) {
			ACCENT_MAP[ plain ].split( '' ).forEach( function ( accented ) {
				lookup[ accented ] = plain;
			} );
		} );
		return lookup;
	} )();

	function stripAccents( text ) {
		return ( text || '' ).replace( /[^\u0000-\u007F]/g, function ( ch ) {
			var lower = ACCENT_LOOKUP[ ch.toLowerCase() ];
			if ( ! lower ) {
				return ch;
			}
			return ch === ch.toLowerCase() ? lower : lower.toUpperCase();
		} );
	}

	/**
	 * Derives a `name` attribute from the field's label when one isn't set
	 * explicitly — same fallback the reference block's save.js applies via
	 * getNameFromLabel(), so a field never submits with an empty `name`.
	 */
	function slugifyLabel( label ) {
		return stripAccents( ( label || '' ).replace( /<[^>]+>/g, '' ) )
			.replace( /[^a-zA-Z0-9]+/g, '-' )
			.toLowerCase()
			.replace( /(^-+)|(-+$)/g, '' );
	}

	/**
	 * Build the field's inner control element (input/select/textarea), shared
	 * between edit() and save(). For text-like types, edit() renders a real,
	 * directly-editable control (typing on the canvas sets `placeholder`) —
	 * ported from the reference block's edit.js, which never disables its
	 * preview input at all. Confirmed by inspecting the real experimental
	 * core/form-input block live (Gutenberg 23.9.1, gutenberg-form-blocks
	 * experiment): its canvas input is a genuine `<input>` with
	 * `disabled: false`, `value={placeholder}`, `onChange` wired straight to
	 * `setAttributes` — not a disabled mockup requiring the Inspector. Our
	 * earlier version disabled every preview control, which meant the only
	 * way to set a placeholder was the Inspector's Placeholder field; this
	 * matches the real block's click-and-type ergonomics instead.
	 */
	function FieldControl( attributes, isSave, setAttributes ) {
		var type = attributes.type;
		var name = attributes.name || slugifyLabel( attributes.label );
		var required = attributes.required;
		var placeholder = attributes.placeholder;

		var common = {
			name: name,
			required: required || undefined,
			'aria-required': required || undefined,
		};

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

		// Editor-only: type directly into the preview to set `placeholder`,
		// same attribute/onChange shape the reference block uses.
		var editableTextProps = ! isSave && {
			'aria-label': __( 'Optional placeholder text', 'axellcore-atelierclub' ),
			placeholder: placeholder ? undefined : __( 'Optional placeholder…', 'axellcore-atelierclub' ),
			value: placeholder,
			onChange: function ( event ) {
				setAttributes( { placeholder: event.target.value } );
			},
		};

		if ( 'hidden' === type ) {
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
				el( 'option', { value: '' }, placeholder || __( 'Select an option', 'axellcore-atelierclub' ) ),
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

	/**
	 * Editor-only placeholder shown in place of the (otherwise invisible,
	 * unselectable) hidden `<input>` — ported from the reference block's
	 * `.is-input-hidden` treatment. Uses inline styles rather than a
	 * stylesheet rule since this plugin has no `enqueue_block_editor_assets`
	 * hook yet (see docs/ARCHITECTURE.md §3 and this plugin's CLAUDE.md) —
	 * keeping this self-contained avoids depending on that gap being closed.
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
			__( 'Hidden field', 'axellcore-atelierclub' )
		);
	}

	/**
	 * Merge a base className into a wrapperProps object (as produced by
	 * useBlockProps()/useBlockProps.save()), so the block's own root element
	 * IS `.aa-field`/`.aa-consent` — no extra wrapping div, which would
	 * otherwise break `.aa-form-row`'s CSS grid (it expects `.aa-field` as
	 * a direct child).
	 */
	function withBase( wrapperProps, base ) {
		var classes = [ base ];
		if ( wrapperProps && wrapperProps.className ) {
			classes.push( wrapperProps.className );
		}
		return Object.assign( {}, wrapperProps, { className: classes.join( ' ' ) } );
	}

	function FieldWrapper( attributes, labelElement, fieldElement, wrapperProps, isSave ) {
		if ( 'hidden' === attributes.type ) {
			// save(): bare <input type="hidden">, matching the reference's
			// own save.js — no wrapper needed once it's on the frontend.
			// edit(): keep the block's own useBlockProps() wrapper (so
			// selection/toolbar wiring isn't dropped) around a visible
			// placeholder instead of the literal hidden input.
			return isSave ? fieldElement : el( 'div', wrapperProps, HiddenFieldPlaceholder() );
		}

		if ( 'consent' === attributes.variant ) {
			return el(
				'label',
				withBase( wrapperProps, 'aa-consent' ),
				fieldElement,
				labelElement
			);
		}

		return el(
			'div',
			withBase( wrapperProps, 'aa-field' ),
			el(
				'label',
				null,
				labelElement,
				attributes.required ? el( 'span', { className: 'aa-req' }, ' *' ) : null
			),
			fieldElement,
			attributes.hint ? el( 'div', { className: 'aa-hint' }, attributes.hint ) : null
		);
	}

	blocks.registerBlockType( 'axellcore/form-input', {
		icon: ICON,
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var labelEl = el( RichText, {
				tagName: 'span',
				className: 'aa-field-label-text',
				value: attributes.label,
				onChange: function ( v ) {
					setAttributes( { label: v } );
				},
				'aria-label': attributes.label ? __( 'Label', 'axellcore-atelierclub' ) : __( 'Empty label', 'axellcore-atelierclub' ),
				'data-empty': ! attributes.label,
				placeholder: __( 'Field label…', 'axellcore-atelierclub' ),
				allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
			} );

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Field', 'axellcore-atelierclub' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Type', 'axellcore-atelierclub' ),
							value: attributes.type,
							options: TYPE_OPTIONS,
							onChange: function ( v ) {
								setAttributes( { type: v } );
							},
						} ),
						'checkbox' === attributes.type &&
							el( SelectControl, {
								label: __( 'Variant', 'axellcore-atelierclub' ),
								value: attributes.variant,
								options: [
									{ label: __( 'Default', 'axellcore-atelierclub' ), value: 'field' },
									{ label: __( 'Consent (LGPD)', 'axellcore-atelierclub' ), value: 'consent' },
								],
								onChange: function ( v ) {
									setAttributes( { variant: v } );
								},
							} ),
						'select' !== attributes.type &&
							'checkbox' !== attributes.type &&
							'hidden' !== attributes.type &&
							el( TextControl, {
								label: __( 'Placeholder', 'axellcore-atelierclub' ),
								value: attributes.placeholder,
								onChange: function ( v ) {
									setAttributes( { placeholder: v } );
								},
							} ),
						'hidden' === attributes.type &&
							el( TextControl, {
								label: __( 'Value', 'axellcore-atelierclub' ),
								value: attributes.value,
								onChange: function ( v ) {
									setAttributes( { value: v } );
								},
							} ),
						'checkbox' === attributes.type &&
							el( ToggleControl, {
								label: __( 'Checked by default', 'axellcore-atelierclub' ),
								checked: !! attributes.checked,
								onChange: function ( v ) {
									setAttributes( { checked: v } );
								},
							} ),
						'hidden' !== attributes.type &&
							'checkbox' !== attributes.type &&
							el( ToggleControl, {
								label: __( 'Required', 'axellcore-atelierclub' ),
								checked: !! attributes.required,
								onChange: function ( v ) {
									setAttributes( { required: v } );
								},
							} ),
						'field' === attributes.variant &&
							'hidden' !== attributes.type &&
							el( TextControl, {
								label: __( 'Hint text (optional)', 'axellcore-atelierclub' ),
								value: attributes.hint,
								onChange: function ( v ) {
									setAttributes( { hint: v } );
								},
							} ),
						'select' === attributes.type &&
							el( TextareaControl, {
								label: __( 'Options (one per line: Label|value)', 'axellcore-atelierclub' ),
								help: __( 'E.g.: Individual · CPF|cpf', 'axellcore-atelierclub' ),
								value: optionsToText( attributes.options ),
								onChange: function ( v ) {
									setAttributes( { options: textToOptions( v ) } );
								},
							} ),
						'select' === attributes.type &&
							el( TextControl, {
								label: __( 'Field (name) that populates these options', 'axellcore-atelierclub' ),
								help: __( 'Leave empty for a static list. If set, this select starts empty/disabled and assets/js/frontend.js fetches its options from the REST cities endpoint whenever that field changes.', 'axellcore-atelierclub' ),
								value: attributes.citiesSourceName,
								onChange: function ( v ) {
									setAttributes( { citiesSourceName: v } );
								},
							} )
					),
					el(
						PanelBody,
						{ title: __( 'Input mask (advanced)', 'axellcore-atelierclub' ), initialOpen: false },
						el( SelectControl, {
							label: __( 'Mask', 'axellcore-atelierclub' ),
							value: attributes.mask,
							options: MASK_OPTIONS,
							onChange: function ( v ) {
								setAttributes( { mask: v } );
							},
						} ),
						'cpf-cnpj' === attributes.mask &&
							el( TextControl, {
								label: __( 'Field (name) that decides CPF vs. CNPJ', 'axellcore-atelierclub' ),
								help: __( 'Name of the select field whose value ("cpf"/"cnpj") drives this mask.', 'axellcore-atelierclub' ),
								value: attributes.maskSourceName,
								onChange: function ( v ) {
									setAttributes( { maskSourceName: v } );
								},
							} )
					)
				),
				// The `name` attribute is an implementation detail (the HTML
				// attribute used for form submission/REST payload keys), not
				// authoring content — kept under "Advanced" to match where
				// the reference block placed it, separate from the main
				// Field settings above.
				el(
					InspectorControls,
					{ group: 'advanced' },
					el( TextControl, {
						label: __( 'Field name (name attribute)', 'axellcore-atelierclub' ),
						value: attributes.name,
						help: __( 'Leave empty to derive it from the label automatically.', 'axellcore-atelierclub' ),
						onChange: function ( v ) {
							setAttributes( { name: v } );
						},
					} )
				),
				FieldWrapper( attributes, labelEl, FieldControl( attributes, false, setAttributes ), blockProps, false )
			);
		},
		save: function ( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps.save();
			var labelEl = el( RichText.Content, {
				tagName: 'span',
				className: 'aa-field-label-text',
				value: attributes.label,
			} );
			return FieldWrapper( attributes, labelEl, FieldControl( attributes, true ), blockProps, true );
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.richText, window.wp.i18n );
