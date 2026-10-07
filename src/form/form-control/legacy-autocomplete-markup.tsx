/*
 * Markup as saved before the Interactivity directives moved to render time
 * (includes/class-form-directives.php). Only the block deprecations use it, so
 * content saved with the directives still validates and migrates.
 */
import { __ } from '@wordpress/i18n';

export const UF_CODES = [
	'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
	'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
];

export interface AutocompleteMarkupArgs {
	blockProps: Record< string, unknown >;
	isSave: boolean;
	id?: string;
	name: string;
	placeholder?: string;
	postType: string;
	template: string;
	allowNotFound: boolean;
}

/**
 * Markup of the autocomplete over a post type (axell/autocomplete store): the
 * search input, the hidden ID and title fields, the custom-store panel
 * (Nome, UF, Cidade) and the suggestion list. Shared by axell/form-control
 * (type autocomplete) and axell/form-control-reseller.
 *
 * @param args Block props and the source of the search.
 */
export function autocompleteMarkup( args: AutocompleteMarkupArgs ) {
	const { blockProps, isSave, id, name, placeholder, postType, template, allowNotFound } = args;

	if ( ! isSave ) {
		// Same field look as the other controls in the editor (read-only, so it does not grey out).
		return (
			<div { ...blockProps }>
				<input
					type="text"
					className="wp-block-axell-form-control"
					readOnly
					placeholder={ placeholder || __( 'Autocomplete (posts)', 'axellcore-atelierclub' ) }
				/>
			</div>
		);
	}

	// Initial state of the Interactivity store "axell/autocomplete".
	const context = {
		postType,
		template,
		allowNotFound,
		text: '',
		selectedId: '',
		titulo: '',
		open: false,
		notFound: false,
		loading: false,
		custom: false,
		customName: '',
		customUf: '',
		customCity: '',
		cityOptions: [],
		activeIndex: -1,
		options: [],
	};
	const listId = `${ name }-list`;

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
				data-wp-bind--hidden="context.custom"
				aria-autocomplete="list"
				aria-controls={ listId }
				placeholder={ placeholder || undefined }
				data-wp-bind--value="context.text"
				data-wp-bind--aria-expanded="context.open"
				data-wp-on--input="actions.onInput"
			/>
			<input
				type="hidden"
				name={ name }
				data-wp-bind--value="context.selectedId"
			/>
			<input
				type="hidden"
				name={ `${ name }_titulo` }
				data-wp-bind--value="context.titulo"
			/>
			<div className="aa-ac-custom" hidden data-wp-bind--hidden="!context.custom">
				<div className="aa-ac-name">
					<input
						type="text"
						data-field="name"
						aria-label={ __( 'Name', 'axellcore-atelierclub' ) }
						placeholder={ __( 'Name', 'axellcore-atelierclub' ) }
						data-wp-bind--value="context.customName"
						data-wp-on--input="actions.onCustomInput"
					/>
					<button
						type="button"
						className="aa-ac-back"
						aria-label={ __( 'Back to search', 'axellcore-atelierclub' ) }
						data-wp-on--click="actions.backToSearch"
					>
						<svg
							xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 24 24"
							width="18"
							height="18"
							fill="none"
							stroke="currentColor"
							strokeWidth="2"
							aria-hidden="true"
							focusable="false"
						>
							<circle cx="11" cy="11" r="7" />
							<path d="m20 20-3.5-3.5" />
						</svg>
					</button>
				</div>
				<select
					aria-label={ __( 'State code', 'axellcore-atelierclub' ) }
					data-wp-bind--value="context.customUf"
					data-wp-on--change="actions.onCustomUf"
				>
					<option value="">UF</option>
					{ UF_CODES.map( ( uf ) => (
						<option key={ uf } value={ uf }>
							{ uf }
						</option>
					) ) }
				</select>
				<select
					aria-label={ __( 'City', 'axellcore-atelierclub' ) }
					data-field="city"
					disabled
					data-wp-bind--disabled="!context.customUf"
					data-wp-on--change="actions.onCustomCity"
					data-wp-watch="callbacks.renderCities"
				>
					<option value="">{ __( 'Select state', 'axellcore-atelierclub' ) }</option>
				</select>
			</div>
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
