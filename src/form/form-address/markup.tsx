import { BR_STATES } from './states';

export interface AddressArgs {
	blockProps: Record< string, unknown >;
	id?: string;
	name: string;
	placeholder?: string;
	required?: boolean;
	/** Name of the country field this control follows. */
	countryField?: string;
	/** Name of the state field this control follows (city). */
	stateField?: string;
	/** City: search the cities of the UF instead of a plain list. */
	searchable?: boolean;
	/** Country: a fixed country code, sent as a hidden field (no select). */
	fixed?: string;
	/** Editor preview (the fixed country shows a note instead of nothing). */
	isEditor?: boolean;
}

/*
 * Address fields, driven by the axell/address store (view.ts). Each control
 * names the fields it follows (countryField, stateField) in its own context;
 * the store keeps the values by field name, so the links are explicit and do
 * not depend on block order. Each case has its own element (a select and a
 * text field): the one that does not apply is hidden and disabled, so only the
 * active one is submitted.
 */

function linked( a: AddressArgs, links: Record< string, unknown > ) {
	return { ...a.blockProps, 'data-wp-context': JSON.stringify( links ) };
}

export const COUNTRY_NAMES: Record< string, string > = { BR: 'Brasil', US: 'Estados Unidos' };

export function countryMarkup( a: AddressArgs ) {
	if ( a.fixed ) {
		// Fixed country: a hidden field the store reads on load (data-wp-init).
		return (
			<div { ...a.blockProps }>
				<input
					type="hidden"
					name={ a.name }
					value={ a.fixed }
					data-wp-init="callbacks.initCountry"
				/>
				{ a.isEditor && (
					<em className="aac-fixed-country">
						{ `País fixo: ${ COUNTRY_NAMES[ a.fixed ] || a.fixed }` }
					</em>
				) }
			</div>
		);
	}

	return (
		<div { ...a.blockProps }>
			<select
				id={ a.id }
				name={ a.name }
				data-wp-init="callbacks.initCountry"
				data-wp-on--change="actions.onField"
				required={ a.required || undefined }
				aria-required={ a.required || undefined }
			>
				<option value="">—</option>
				<option value="BR">Brasil</option>
				<option value="US">Estados Unidos</option>
				<option value="">Outro</option>
			</select>
		</div>
	);
}

export function stateMarkup( a: AddressArgs ) {
	return (
		<div { ...linked( a, { countryField: a.countryField || 'pais' } ) }>
			<select
				id={ a.id }
				name={ a.name }
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
	const links = { countryField: a.countryField || 'pais', stateField: a.stateField || 'uf' };
	const freeText = (
		<input
			type="text"
			id={ a.searchable ? undefined : a.id }
			name={ a.name }
			placeholder={ a.placeholder || undefined }
			data-wp-bind--hidden="state.hasCityList"
			data-wp-bind--disabled="state.hasCityList"
			data-wp-on--input="actions.onField"
			required={ a.required || undefined }
		/>
	);

	if ( a.searchable ) {
		// Search over the cities of the UF: the visible input has no name, the
		// hidden one carries the IBGE code of the chosen city.
		const listId = `${ a.name }-cities`;
		return (
			<div
				{ ...linked( a, { ...links, query: '', code: '', open: false, active: -1 } ) }
				className={ `${ ( a.blockProps.className as string ) || '' } aac-city-search`.trim() }
				data-wp-on--focusout="actions.onCityFocusOut"
			>
				<input
					type="text"
					id={ a.id }
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
		<div { ...linked( a, links ) }>
			<select
				name={ a.name }
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
		<div { ...linked( a, { countryField: a.countryField || 'pais' } ) }>
			<input
				type="text"
				id={ a.id }
				name={ a.name }
				inputMode="numeric"
				placeholder={ a.placeholder || undefined }
				data-wp-on--input="actions.onPostalInput"
				required={ a.required || undefined }
			/>
		</div>
	);
}
