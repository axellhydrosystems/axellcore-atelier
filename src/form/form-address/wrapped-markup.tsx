/*
 * v2 markup: a wrapper div around the plain field (the directives already on
 * render), before single-field controls saved the field as the block root.
 * Only the block deprecations use it.
 */
import { HiddenFieldPlaceholder } from '../form-control/control-element';
import { COUNTRY_NAMES, type AddressArgs } from './markup';

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

