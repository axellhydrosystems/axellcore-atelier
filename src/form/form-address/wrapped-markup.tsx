/*
 * v2 markup: a wrapper div around the plain field (the directives already on
 * render), before single-field controls saved the field as the block root.
 * Only the block deprecations use it.
 */
import { HiddenFieldPlaceholder } from '../form-control/control-element';
import { COUNTRY_NAMES, type AddressArgs } from './markup';
import { BR_STATES } from './states';

const FIELD = 'wp-block-axell-form-control';

function region( a: AddressArgs ): Record< string, unknown > {
	return { ...a.blockProps };
}

export function wrappedCountryMarkup( a: AddressArgs ) {
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
			<div { ...region( a ) }>
				<input type="hidden" name={ a.name } value={ a.fixed || '' } />
			</div>
		);
	}

	return (
		<div { ...region( a ) }>
			<select
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				defaultValue={ a.fixed || '' }
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

export function wrappedPostalMarkup( a: AddressArgs ) {
	return (
		<div { ...region( a ) }>
			<input
				type="text"
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				inputMode="numeric"
				placeholder={ a.placeholder || undefined }
				required={ a.required || undefined }
			/>
		</div>
	);
}

export function wrappedPhoneMarkup( a: AddressArgs ) {
	return (
		<div { ...region( a ) }>
			<input
				type="tel"
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				autoComplete="tel"
				placeholder={ a.placeholder || undefined }
				required={ a.required || undefined }
				aria-required={ a.required || undefined }
			/>
		</div>
	);
}

export function wrappedStateMarkup( a: AddressArgs ) {
	return (
		<div { ...region( a ) }>
			<select
				id={ a.id }
				name={ a.name }
				className={ FIELD }
				hidden
				disabled
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
				required={ a.required || undefined }
			/>
		</div>
	);
}

export function wrappedCityMarkup( a: AddressArgs ) {
	const freeText = (
		<input
			type="text"
			id={ a.searchable ? undefined : a.id }
			name={ a.name }
			className={ FIELD }
			placeholder={ a.placeholder || undefined }
			required={ a.required || undefined }
		/>
	);

	if ( a.searchable ) {
		// Search over the cities of the state: the visible input has no name, the
		// hidden one carries the IBGE code of the chosen city.
		const listId = `${ a.name }-cities`;
		const props = region( a );
		return (
			<div
				{ ...props }
				className={ `${ ( props.className as string ) || '' } aa-city-search`.trim() }
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
					required={ a.required || undefined }
				/>
				<input
					type="hidden"
					name={ a.name }
					disabled
				/>
				{ freeText }
				<ul
					id={ listId }
					role="listbox"
					hidden
				/>
			</div>
		);
	}

	return (
		<div { ...region( a ) }>
			<select
				name={ a.name }
				className={ FIELD }
				hidden
				disabled
				required={ a.required || undefined }
			>
				<option value="">—</option>
			</select>
			{ freeText }
		</div>
	);
}
