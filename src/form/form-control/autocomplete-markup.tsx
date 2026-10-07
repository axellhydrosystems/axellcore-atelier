import { __ } from '@wordpress/i18n';

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
	// postType, template and allowNotFound feed the render-time context (PHP).
	const { blockProps, isSave, id, placeholder } = args;

	if ( ! isSave ) {
		// Same field look as the other controls in the editor (read-only, so it does not grey out).
		// The field is the block root, as saved, so its style attributes show here.
		return (
			<input
				{ ...blockProps }
				className={ [ blockProps.className as string | undefined, 'wp-block-axell-form-control' ].filter( Boolean ).join( ' ' ) }
				type="text"
				readOnly
				tabIndex={ -1 }
				placeholder={ placeholder || __( 'Autocomplete (posts)', 'axellcore-atelierclub' ) }
			/>
		);
	}

	// Saved: the search field only. The widget around it (wrapper region,
	// hidden ID and title fields, custom-store panel, suggestion list) and
	// the axell/autocomplete directives are built on render from the block
	// attributes (includes/class-form-directives.php).
	const className = [ blockProps.className as string | undefined, 'wp-block-axell-form-control' ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<input
			{ ...blockProps }
			className={ className }
			type="text"
			id={ id }
			autoComplete="off"
			placeholder={ placeholder || undefined }
		/>
	);
}
