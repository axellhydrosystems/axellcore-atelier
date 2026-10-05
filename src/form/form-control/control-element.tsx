import { __ } from '@wordpress/i18n';
import type { FormControlAttributes } from './types';

/**
 * Editor-only placeholder shown in place of the (otherwise invisible,
 * unselectable) hidden `<input>` — ported from the reference block's
 * `.is-input-hidden` treatment. Inline styles, not a stylesheet rule —
 * self-contained regardless of which page this block ends up on.
 */
function HiddenFieldPlaceholder() {
	return (
		<span
			className="aac-field-hidden-placeholder"
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
			{ __( 'Hidden field', 'axellcore-atelierclub' ) }
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
	blockProps: Record< string, unknown >
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
	};

	if ( isSave ) {
		common.placeholder = placeholder || undefined;
		if ( attributes.mask ) {
			common[ 'data-aac-mask' ] = attributes.mask;
		}
		if ( attributes.maskSourceName ) {
			common[ 'data-aac-mask-source' ] = attributes.maskSourceName;
		}
		if ( attributes.citiesSourceName ) {
			common[ 'data-aac-cities-source' ] = attributes.citiesSourceName;
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
					'axellcore-atelierclub'
				),
				placeholder: placeholder
					? undefined
					: __( 'Optional placeholder…', 'axellcore-atelierclub' ),
				value: placeholder,
				onChange: (
					event: React.ChangeEvent<
						HTMLInputElement | HTMLTextAreaElement
					>
				) => setAttributes( { placeholder: event.target.value } ),
			}
		: {};

	if ( type === 'autocomplete' ) {
		if ( ! isSave ) {
			return (
				<div { ...blockProps }>
					<input
						type="text"
						disabled
						placeholder={ __(
							'Autocomplete (posts)',
							'axellcore-atelierclub'
						) }
					/>
				</div>
			);
		}

		// Initial state of the Interactivity store "axell/autocomplete" for this control.
		const context = {
			postType: attributes.sourcePostType || '',
			template: attributes.labelTemplate || '[post_title]',
			allowNotFound: !! attributes.allowNotFound,
			text: '',
			selectedId: '',
			titulo: '',
			open: false,
			notFound: false,
			activeIndex: -1,
			options: [],
		};
		const listId = `${ name as string }-list`;

		return (
			<div
				{ ...blockProps }
				data-wp-interactive="axell/autocomplete"
				data-wp-context={ JSON.stringify( context ) }
				data-wp-on--keydown="actions.onKeydown"
				data-wp-on--focusout="actions.onFocusOut"
			>
				<input
					type="text"
					id={ id }
					autoComplete="off"
					role="combobox"
					aria-autocomplete="list"
					aria-controls={ listId }
					placeholder={ placeholder || undefined }
					data-wp-bind--value="context.text"
					data-wp-bind--aria-expanded="context.open"
					data-wp-on--input="actions.onInput"
				/>
				<input
					type="hidden"
					name={ name as string }
					data-wp-bind--value="context.selectedId"
				/>
				<input
					type="hidden"
					name={ `${ name as string }_titulo` }
					data-wp-bind--value="context.titulo"
				/>
				<ul
					id={ listId }
					role="listbox"
					hidden
					data-wp-bind--hidden="!context.open"
					data-wp-on--click="actions.pick"
					data-wp-on--mousedown="actions.keepFocus"
					data-wp-watch="callbacks.renderList"
				/>
			</div>
		);
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
						__( 'Select an option', 'axellcore-atelierclub' ) }
				</option>
				{ options.map( ( o, i ) => (
					<option key={ i } value={ o.value }>
						{ o.label }
					</option>
				) ) }
			</select>
		);
	}

	if ( type === 'checkbox' ) {
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
