import { __ } from '@wordpress/i18n';
import type { FormInputAttributes } from './types';

/**
 * Shared field-rendering logic for axellcore/form-input — DEPRECATED, kept
 * registered only (hidden from the inserter via block.json's
 * `"inserter": false`) so any pre-existing content that used it keeps
 * rendering/editing correctly. Superseded by axell/form-label +
 * axell/form-control (see this plugin's CLAUDE.md for why: Gutenberg's
 * `deprecated`/`migrate` API can't automatically split one block into
 * sibling blocks of a different name on page load — confirmed by reading
 * core/gallery's own v1→v2 migration — so this logic is NOT touched
 * further beyond the mechanical JS→TS/build conversion; new content is
 * generated directly as the new blocks by bin/generate-content.py
 * instead). Everything below is unchanged legacy behavior.
 */

// Accent map mirrors the one already proven server-side in
// includes/class-locations.php (sanitize_title()) — kept here instead of
// adding the reference block's `remove-accents` npm dependency.
const ACCENT_MAP: Record< string, string > = {
	a: 'áàãâä',
	e: 'éèêë',
	i: 'íìîï',
	o: 'óòõôö',
	u: 'úùûü',
	c: 'ç',
	n: 'ñ',
};

const ACCENT_LOOKUP: Record< string, string > = ( () => {
	const lookup: Record< string, string > = {};
	Object.keys( ACCENT_MAP ).forEach( ( plain ) => {
		ACCENT_MAP[ plain ].split( '' ).forEach( ( accented ) => {
			lookup[ accented ] = plain;
		} );
	} );
	return lookup;
} )();

function stripAccents( text: string ): string {
	return ( text || '' ).replace( /[^\u0000-\u007F]/g, ( ch ) => {
		const lower = ACCENT_LOOKUP[ ch.toLowerCase() ];
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
 * @param label
 */
export function slugifyLabel( label: string ): string {
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
 * preview input at all.
 * @param attributes
 * @param isSave
 * @param setAttributes
 */
export function FieldControl(
	attributes: FormInputAttributes,
	isSave: boolean,
	setAttributes?: ( attrs: Partial< FormInputAttributes > ) => void
) {
	const type = attributes.type;
	const name = attributes.name || slugifyLabel( attributes.label );
	const required = attributes.required;
	const placeholder = attributes.placeholder;

	const common: Record< string, unknown > = {
		name,
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
	const editableTextProps =
		! isSave && setAttributes
			? {
					'aria-label': __(
						'Optional placeholder text',
						'axellcore-atelier'
					),
					placeholder: placeholder
						? undefined
						: __(
								'Optional placeholder…',
								'axellcore-atelier'
							),
					value: placeholder,
					onChange: (
						event: React.ChangeEvent<
							HTMLInputElement | HTMLTextAreaElement
						>
					) => {
						setAttributes( { placeholder: event.target.value } );
					},
				}
			: {};

	if ( 'hidden' === type ) {
		return (
			<input
				type="hidden"
				name={ name }
				value={ attributes.value || '' }
			/>
		);
	}

	if ( 'textarea' === type ) {
		return <textarea { ...common } { ...editableTextProps } />;
	}

	if ( 'select' === type ) {
		const options = attributes.options || [];
		return (
			<select { ...common }>
				<option value="">
					{ placeholder ||
						__( 'Select an option', 'axellcore-atelier' ) }
				</option>
				{ options.map( ( o, i ) => (
					<option key={ i } value={ o.value }>
						{ o.label }
					</option>
				) ) }
			</select>
		);
	}

	if ( 'checkbox' === type ) {
		return (
			<input
				{ ...common }
				type="checkbox"
				checked={ isSave ? undefined : !! attributes.checked }
				defaultChecked={ isSave ? !! attributes.checked : undefined }
			/>
		);
	}

	return <input { ...common } { ...editableTextProps } type={ type } />;
}

/**
 * Editor-only placeholder shown in place of the (otherwise invisible,
 * unselectable) hidden `<input>` — ported from the reference block's
 * `.is-input-hidden` treatment. Uses inline styles rather than a
 * stylesheet rule since this plugin has no `enqueue_block_editor_assets`
 * hook yet.
 */
export function HiddenFieldPlaceholder() {
	return (
		<span
			className="aa-field-hidden-placeholder"
			style={ {
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
				boxSizing: 'border-box',
				width: '100%',
				padding: '0.6em',
				fontSize: '0.85em',
				opacity: 0.6,
				border: '1px dashed currentColor',
			} }
		>
			{ __( 'Hidden field', 'axellcore-atelier' ) }
		</span>
	);
}

/**
 * Merge a base className into a wrapperProps object (as produced by
 * useBlockProps()/useBlockProps.save()), so the block's own root element
 * IS `.aa-field`/`.aa-consent` — no extra wrapping div, which would
 * otherwise break `.aa-form-row`'s CSS grid (it expects `.aa-field` as
 * a direct child).
 * @param wrapperProps
 * @param base
 */
function withBase(
	wrapperProps: Record< string, unknown >,
	base: string
): Record< string, unknown > {
	const classes = [ base ];
	if (
		wrapperProps &&
		typeof wrapperProps.className === 'string' &&
		wrapperProps.className
	) {
		classes.push( wrapperProps.className );
	}
	return { ...wrapperProps, className: classes.join( ' ' ) };
}

export function FieldWrapper(
	attributes: FormInputAttributes,
	labelElement: React.ReactNode,
	fieldElement: React.ReactNode,
	wrapperProps: Record< string, unknown >,
	isSave: boolean
) {
	if ( 'hidden' === attributes.type ) {
		// save(): bare <input type="hidden">, matching the reference's
		// own save.js — no wrapper needed once it's on the frontend.
		// edit(): keep the block's own useBlockProps() wrapper (so
		// selection/toolbar wiring isn't dropped) around a visible
		// placeholder instead of the literal hidden input.
		return isSave ? (
			fieldElement
		) : (
			<div { ...wrapperProps }>
				<HiddenFieldPlaceholder />
			</div>
		);
	}

	if ( 'consent' === attributes.variant ) {
		return (
			// eslint-disable-next-line jsx-a11y/label-has-associated-control -- frozen legacy markup: implicit <label>{ control }{ text }</label> nesting is the association (superseded by axell/form-label's explicit for/id pairing; this deprecated block's rendering is intentionally left unchanged).
			<label { ...withBase( wrapperProps, 'aa-consent' ) }>
				{ fieldElement }
				{ labelElement }
			</label>
		);
	}

	return (
		<div { ...withBase( wrapperProps, 'aa-field' ) }>
			{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control -- same frozen legacy pattern as the consent branch above. */ }
			<label>
				{ labelElement }
				{ attributes.required ? (
					<span className="aa-req"> *</span>
				) : null }
			</label>
			{ fieldElement }
			{ attributes.hint ? (
				<div className="aa-hint">{ attributes.hint }</div>
			) : null }
		</div>
	);
}
