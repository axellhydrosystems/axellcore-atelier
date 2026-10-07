/*
 * v2 markup: the whole widget saved (wrapper, hidden fields, custom panel,
 * list) without directives, before the save became the search field only
 * and the rest moved to render (includes/class-form-directives.php). Only
 * the block deprecations use it.
 */
import { __ } from '@wordpress/i18n';

const UF_CODES = [
	'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
	'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
];

interface AutocompleteMarkupArgs {
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
export function wrappedAutocompleteMarkup( args: AutocompleteMarkupArgs ) {
	// postType, template and allowNotFound feed the render-time context (PHP).
	const { blockProps, isSave, id, name, placeholder } = args;

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

	// The axell/autocomplete directives and initial context are added on render
	// (includes/class-form-directives.php), from the block attributes.
	const listId = `${ name }-list`;

	return (
		<div
			{ ...blockProps }
		>
			<input
				type="text"
				id={ id }
				autoComplete="off"
				role="combobox"
				aria-autocomplete="list"
				aria-controls={ listId }
				placeholder={ placeholder || undefined }
			/>
			<input
				type="hidden"
				name={ name }
			/>
			<input
				type="hidden"
				name={ `${ name }_titulo` }
			/>
			<div className="aa-ac-custom" hidden>
				<div className="aa-ac-name">
					<input
						type="text"
						data-field="name"
						aria-label={ __( 'Name', 'axellcore-atelierclub' ) }
						placeholder={ __( 'Name', 'axellcore-atelierclub' ) }
					/>
					<button
						type="button"
						className="aa-ac-back"
						aria-label={ __( 'Back to search', 'axellcore-atelierclub' ) }
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
				>
					<option value="">{ __( 'Select state', 'axellcore-atelierclub' ) }</option>
				</select>
			</div>
			<ul
				id={ listId }
				role="listbox"
				hidden
			/>
		</div>
	);
}
