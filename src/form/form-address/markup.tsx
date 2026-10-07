import { HiddenFieldPlaceholder } from '../form-control/control-element';

export interface AddressArgs {
	blockProps: Record< string, unknown >;
	id?: string;
	name: string;
	placeholder?: string;
	required?: boolean;
	/** Name of the country field this control follows. */
	countryField?: string;
	/** Where the country comes from: a field of the form, or the settings. */
	countrySource?: string;
	/** Country chosen in the settings (countrySource "select"; '' = Outro). */
	country?: string;
	/** Name of the state field this control follows (city). */
	stateField?: string;
	/** City: search the cities of the state instead of a plain list. */
	searchable?: boolean;
	/** Country: the default country code (preselected, or sent when hidden). */
	fixed?: string;
	/** Country: sent as a hidden field instead of a select. */
	hiddenField?: boolean;
	/** Phone: line type (Brazil only): both, mobile or landline. */
	lineType?: string;
	/** Editor preview (the hidden country shows a placeholder). */
	isEditor?: boolean;
}

export const COUNTRY_NAMES: Record< string, string > = { BR: 'Brasil', US: 'Estados Unidos' };

/** Field look of the form controls (src/form/form-control/style.scss). */
const FIELD = 'wp-block-axell-form-control';

/*
 * Address controls. Each one is its own region of the axell/address store
 * (view.ts) and names, in its context, the fields it follows (countryField,
 * stateField). The values live in the store state by form and field name, so
 * the controls only need to be in the same form, in any form group. Each case
 * has its own element (a select and a text field): the one that does not apply
 * is hidden and disabled, so only the active one is submitted.
 */
/**
 * The chosen country as a code: upper case, and Brazil when the block has none
 * stored (blocks saved before the country was written out).
 */
export function chosenCountry( country?: string ): string {
	return ( country ?? 'BR' ).toUpperCase();
}

/*
 * The Interactivity directives (store, context, events, bindings) are added
 * on render from the block attributes (includes/class-form-directives.php),
 * so the saved markup is the plain HTML: elements, ids, names, hidden and
 * disabled, options.
 */
function region( a: AddressArgs ): Record< string, unknown > {
	return { ...a.blockProps };
}

/**
 * A single-field control saves the field itself as the block root (no wrapper):
 * the block props go on it, with the field look class.
 */
function fieldProps( a: AddressArgs ): Record< string, unknown > {
	const className = [ a.blockProps.className as string | undefined, FIELD ].filter( Boolean ).join( ' ' );
	return { ...a.blockProps, className };
}

export function countryMarkup( a: AddressArgs ) {
	if ( a.hiddenField ) {
		if ( a.isEditor ) {
			return (
				<div { ...a.blockProps }>
					<HiddenFieldPlaceholder
						label={ `Campo oculto: País = ${ COUNTRY_NAMES[ a.fixed || '' ] || a.fixed || '—' }` }
					/>
				</div>
			);
		}
		return <input { ...a.blockProps } type="hidden" name={ a.name } value={ a.fixed || '' } />;
	}

	return (
		<select
			{ ...fieldProps( a ) }
			id={ a.id }
			name={ a.name }
			defaultValue={ a.fixed || '' }
			required={ a.required || undefined }
			aria-required={ a.required || undefined }
		>
			<option value="BR">Brasil</option>
			<option value="US">Estados Unidos</option>
			{ /* Outro: no country code (sent empty, state and city as free text). */ }
			<option value="">Outro</option>
		</select>
	);
}

/** Countries whose states the control lists in a select (states.ts). */
const STATE_LISTS = [ 'BR', 'US' ];

export function stateMarkup( a: AddressArgs ) {
	if ( a.isEditor ) {
		// The editor has no interactivity to switch the two elements: show the
		// one the settings make active. A chosen country with a state list is
		// a select whose first option is the placeholder; else a text field.
		// The field is the block root, as saved, so its style attributes show here.
		const list = a.countrySource === 'select' && STATE_LISTS.includes( chosenCountry( a.country ) );
		return list ? (
			<select { ...fieldProps( a ) } id={ a.id } defaultValue="" tabIndex={ -1 }>
				<option value="">{ a.placeholder || '—' }</option>
			</select>
		) : (
			<input
				{ ...fieldProps( a ) }
				type="text"
				id={ a.id }
				placeholder={ a.placeholder || undefined }
				readOnly
				tabIndex={ -1 }
			/>
		);
	}
	// Only the field: the render (Form_Directives::state()) adds the wrapper,
	// the states of the country and the free-text input for other countries.
	return (
		<select
			{ ...fieldProps( a ) }
			id={ a.id }
			name={ a.name }
			disabled
			required={ a.required || undefined }
		>
			<option value="">{ a.placeholder || '—' }</option>
		</select>
	);
}

export function cityMarkup( a: AddressArgs ) {
	if ( a.isEditor ) {
		return (
			<input
				{ ...fieldProps( a ) }
				type="text"
				id={ a.id }
				placeholder={ a.placeholder || undefined }
				readOnly
				tabIndex={ -1 }
			/>
		);
	}
	// Only the field: the render (Form_Directives::city()) builds the search
	// (or the list) of the cities of the state around it.
	return (
		<input
			{ ...fieldProps( a ) }
			type="text"
			id={ a.id }
			autoComplete="off"
			placeholder={ a.placeholder || undefined }
			required={ a.required || undefined }
		/>
	);
}

export function postalMarkup( a: AddressArgs ) {
	return (
		<input
			{ ...fieldProps( a ) }
			type="text"
			id={ a.id }
			name={ a.name }
			inputMode="numeric"
			placeholder={ a.placeholder || undefined }
			required={ a.required || undefined }
		/>
	);
}

export function phoneMarkup( a: AddressArgs ) {
	return (
		<input
			{ ...fieldProps( a ) }
			type="tel"
			id={ a.id }
			name={ a.name }
			autoComplete="tel"
			placeholder={ a.placeholder || undefined }
			required={ a.required || undefined }
			aria-required={ a.required || undefined }
		/>
	);
}
