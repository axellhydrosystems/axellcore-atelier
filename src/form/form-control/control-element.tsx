import { __ } from '@wordpress/i18n';
import type { FormControlAttributes } from './types';
import { autocompleteMarkup } from './autocomplete-markup';

/**
 * Editor-only placeholder shown in place of the (otherwise invisible,
 * unselectable) hidden `<input>` — ported from the reference block's
 * `.is-input-hidden` treatment. Inline styles, not a stylesheet rule —
 * self-contained regardless of which page this block ends up on.
 */
export function HiddenFieldPlaceholder( { label }: { label?: string } = {} ) {
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
				// Editor only: keeps the next field off the placeholder.
				marginBlockEnd: '1rem',
				fontSize: '0.85em',
				opacity: 0.6,
				border: '1px dashed currentColor',
			} }
		>
			{ label || __( 'Hidden field', 'axellcore-atelier' ) }
		</span>
	);
}

/**
 * Build the control element, shared between edit() and save(). `name`
 * falls back to `id` when left blank (no `label` attribute lives on this
 * block anymore to derive a slug from — that fallback is
 * axell/form-label's own concern if it ever needs one).
 *
 * Unlike the reference block's own save.jsx (a wrapping `<div>` around a
 * separate inner `<input>`, needing manual `getColorClassesAndStyles()`/
 * `getBorderClassesAndStyles()` merging), this block's root element IS the
 * control itself — `blockProps` (from `useBlockProps()`/`useBlockProps.save()`)
 * merged directly onto it, so the native color/typography/border supports
 * apply with zero extra plumbing.
 * @param attributes
 * @param isSave
 * @param setAttributes
 * @param blockProps
 */
export default function ControlElement(
	attributes: FormControlAttributes,
	isSave: boolean,
	setAttributes: ( attrs: Partial< FormControlAttributes > ) => void,
	blockProps: Record< string, unknown >,
	/** Renders the autocomplete type (the deprecation passes the legacy one). */
	renderAutocomplete: typeof autocompleteMarkup = autocompleteMarkup,
	/**
	 * Editor: whether the placeholder is being edited in the field. Out of
	 * focus the field shows the real placeholder (as on the front); in focus
	 * its text is the placeholder, to edit in place.
	 */
	editing?: { active: boolean; set: ( active: boolean ) => void }
) {
	const { type, required, placeholder } = attributes;
	const id = attributes.id || undefined;
	const name = attributes.name || attributes.id || undefined;

	const common: Record< string, unknown > = {
		...blockProps,
		id,
		name,
		required: required || undefined,
		'aria-required': required || undefined,
		autoComplete: attributes.autofill || undefined,
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

	const editableTextProps: Record< string, unknown > = ! isSave
		? {
				'aria-label': __(
					'Optional placeholder text',
					'axellcore-atelier'
				),
				placeholder:
					editing?.active && placeholder
						? undefined
						: placeholder || __( 'Optional placeholder…', 'axellcore-atelier' ),
				value: editing && ! editing.active ? '' : placeholder,
				onFocus: () => editing?.set( true ),
				onBlur: () => editing?.set( false ),
				onChange: (
					event: React.ChangeEvent<
						HTMLInputElement | HTMLTextAreaElement
					>
				) => setAttributes( { placeholder: event.target.value } ),
			}
		: {};

	if ( type === 'autocomplete' ) {
		return renderAutocomplete( {
			blockProps,
			isSave,
			id,
			name: name as string,
			placeholder: placeholder || undefined,
			postType: attributes.sourcePostType || '',
			template: attributes.labelTemplate || '[post_title]',
			allowNotFound: !! attributes.allowNotFound,
		} );
	}

	if ( type === 'hidden' ) {
		if ( ! isSave ) {
			return (
				<div { ...blockProps }>
					<HiddenFieldPlaceholder />
				</div>
			);
		}
		return (
			<input
				type="hidden"
				name={ name as string }
				value={ attributes.value || '' }
			/>
		);
	}

	if ( type === 'textarea' ) {
		return <textarea { ...common } { ...editableTextProps } />;
	}

	if ( type === 'select' ) {
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

	return <input { ...common } { ...editableTextProps } type={ type } />;
}
