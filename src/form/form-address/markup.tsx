import { BR_STATES } from './states';
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
/** How a control finds its country: the chosen one, or the linked field. */
/**
 * The chosen country as a code: upper case, and Brazil when the block has none
 * stored (blocks saved before the country was written out).
 */
export function chosenCountry( country?: string ): string {
	return ( country ?? 'BR' ).toUpperCase();
}

function countryLink( a: AddressArgs ): Record< string, unknown > {
	return a.countrySource === 'select'
		? { fixedCountry: chosenCountry( a.country ) }
		: { countryField: a.countryField || 'pais' };
}

function region( a: AddressArgs, context: Record< string, unknown > ): Record< string, unknown > {
	return {
		...a.blockProps,
		'data-wp-interactive': 'axell/address',
		// form: the id of the form this control belongs to, set on load.
		'data-wp-context': JSON.stringify( { form: '', ...context } ),
		'data-wp-init--region': 'callbacks.initRegion',
	};
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
		return (
			<div { ...region( a, {} ) }>
				<input type="hidden" name={ a.name } value={ a.fixed || '' } data-wp-init="callbacks.initCountry" />
			</div>
		);
	}

	return (
		<div { ...region( a, {} ) }>
			<select
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				defaultValue={ a.fixed || '' }
				data-wp-init="callbacks.initCountry"
				data-wp-on--change="actions.onField"
				required={ a.required || undefined }
				aria-required={ a.required || undefined }
			>
				<option value="BR">Brasil</option>
				<option value="US">Estados Unidos</option>
				{ /* Outro: no country code (sent empty, state and city as free text). */ }
				<option value="">Outro</option>
			</select>
		</div>
	);
}

/** Countries whose states the control lists in a select (states.ts). */
const STATE_LISTS = [ 'BR', 'US' ];

export function stateMarkup( a: AddressArgs ) {
	if ( a.isEditor ) {
		// The editor has no interactivity to switch the two elements: show the
		// one the settings make active. A chosen country with a state list is
		// a select whose first option is the placeholder; else a text field.
		const list = a.countrySource === 'select' && STATE_LISTS.includes( chosenCountry( a.country ) );
		return (
			<div { ...a.blockProps }>
				{ list ? (
					<select id={ a.id } className={ FIELD } defaultValue="" tabIndex={ -1 }>
						<option value="">{ a.placeholder || '—' }</option>
					</select>
				) : (
					<input
						type="text"
						id={ a.id }
						className={ FIELD }
						placeholder={ a.placeholder || undefined }
						readOnly
						tabIndex={ -1 }
					/>
				) }
			</div>
		);
	}
	return (
		<div { ...region( a, countryLink( a ) ) }>
			<select
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				hidden
				disabled
				data-wp-bind--hidden="!state.hasStateList"
				data-wp-bind--disabled="!state.hasStateList"
				data-wp-on--change="actions.onState"
				data-wp-watch="callbacks.renderStates"
				required={ a.required || undefined }
			>
				<option value="">—</option>
				{ BR_STATES.map( ( uf ) => (
					<option key={ uf } value={ uf }>
						{ uf }
					</option>
				) ) }
			</select>
			<input
				type="text"
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				placeholder={ a.placeholder || undefined }
				data-wp-bind--hidden="state.hasStateList"
				data-wp-bind--disabled="state.hasStateList"
				data-wp-on--input="actions.onField"
				required={ a.required || undefined }
			/>
		</div>
	);
}

export function cityMarkup( a: AddressArgs ) {
	const links = { ...countryLink( a ), stateField: a.stateField || 'uf' };
	const freeText = (
		<input
			type="text"
			id={ a.searchable ? undefined : a.id }
			name={ a.name }
			className={ FIELD }
			placeholder={ a.placeholder || undefined }
			data-wp-bind--hidden="state.hasCityList"
			data-wp-bind--disabled="state.hasCityList"
			data-wp-on--input="actions.onField"
			required={ a.required || undefined }
		/>
	);

	if ( a.searchable ) {
		// Search over the cities of the state: the visible input has no name, the
		// hidden one carries the IBGE code of the chosen city.
		const listId = `${ a.name }-cities`;
		const props = region( a, { ...links, query: '', code: '', open: false, active: -1 } );
		return (
			<div
				{ ...props }
				className={ `${ ( props.className as string ) || '' } aa-city-search`.trim() }
				data-wp-on--focusout="actions.onCityFocusOut"
			>
				<input
					type="text"
					id={ a.id }
					className={ FIELD }
					role="combobox"
					autoComplete="off"
					aria-autocomplete="list"
					aria-controls={ listId }
					placeholder={ a.placeholder || undefined }
					hidden
					disabled
					data-wp-bind--hidden="!state.hasCityList"
					data-wp-bind--disabled="!state.hasCityList"
					data-wp-bind--value="context.query"
					data-wp-bind--aria-expanded="context.open"
					data-wp-on--input="actions.onCitySearch"
					data-wp-on--keydown="actions.onCityKeydown"
					required={ a.required || undefined }
				/>
				<input
					type="hidden"
					name={ a.name }
					disabled
					data-wp-bind--disabled="!state.hasCityList"
					data-wp-bind--value="context.code"
				/>
				{ freeText }
				<ul
					id={ listId }
					role="listbox"
					hidden
					data-wp-bind--hidden="!state.cityListOpen"
					data-wp-on--click="actions.pickCity"
					data-wp-on--mousedown="actions.keepFocus"
					data-wp-watch="callbacks.renderCitySearch"
				/>
			</div>
		);
	}

	return (
		<div { ...region( a, links ) }>
			<select
				name={ a.name }
				className={ FIELD }
				hidden
				disabled
				data-wp-bind--hidden="!state.hasCityList"
				data-wp-bind--disabled="!state.hasCityList"
				data-wp-on--change="actions.onField"
				data-wp-watch="callbacks.renderCities"
				required={ a.required || undefined }
			>
				<option value="">—</option>
			</select>
			{ freeText }
		</div>
	);
}

export function postalMarkup( a: AddressArgs ) {
	return (
		<div { ...region( a, countryLink( a ) ) }>
			<input
				type="text"
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				inputMode="numeric"
				placeholder={ a.placeholder || undefined }
				data-wp-on--input="actions.onPostalInput"
				required={ a.required || undefined }
			/>
		</div>
	);
}

export function phoneMarkup( a: AddressArgs ) {
	// The line type only goes into the context when it narrows the number, so
	// phones saved before it ("both") keep their markup.
	const line = a.lineType && a.lineType !== 'both' ? { lineType: a.lineType } : {};
	return (
		<div { ...region( a, { ...countryLink( a ), ...line } ) }>
			<input
				type="tel"
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				autoComplete="tel"
				placeholder={ a.placeholder || undefined }
				data-wp-on--input="actions.onPhoneInput"
				required={ a.required || undefined }
				aria-required={ a.required || undefined }
			/>
		</div>
	);
}
