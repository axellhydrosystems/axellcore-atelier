/**
 * Per-type inserter variations for axell/form-control — picking a control
 * type (Text/Email/URL/Phone/Number/Textarea/Select/Checkbox/Hidden) at
 * insertion time, instead of only via the Inspector's Type dropdown
 * (index.js) after the fact. Ported verbatim from
 * axellcore/form-input/variations.js (which itself mirrors Gutenberg's
 * removed experimental core/form-input block — see this plugin's CLAUDE.md).
 */
( function ( blocks, i18n ) {
	var __ = i18n.__;

	function isType( type ) {
		return function ( blockAttributes ) {
			var current = blockAttributes && blockAttributes.type;
			return current === type || ( ! current && 'text' === type );
		};
	}

	var VARIATIONS = [
		{
			name: 'text',
			title: __( 'Text Control', 'axellcore-atelier' ),
			description: __( 'A generic text input.', 'axellcore-atelier' ),
			attributes: { type: 'text' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'text' ),
		},
		{
			name: 'email',
			title: __( 'Email Control', 'axellcore-atelier' ),
			description: __( 'Used for email addresses.', 'axellcore-atelier' ),
			attributes: { type: 'email' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'email' ),
		},
		{
			name: 'url',
			title: __( 'URL Control', 'axellcore-atelier' ),
			description: __( 'Used for URLs.', 'axellcore-atelier' ),
			attributes: { type: 'url' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'url' ),
		},
		{
			name: 'tel',
			title: __( 'Phone Control', 'axellcore-atelier' ),
			description: __( 'Used for phone numbers.', 'axellcore-atelier' ),
			attributes: { type: 'tel' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'tel' ),
		},
		{
			name: 'number',
			title: __( 'Number Control', 'axellcore-atelier' ),
			description: __( 'A numeric input.', 'axellcore-atelier' ),
			attributes: { type: 'number' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'number' ),
		},
		{
			name: 'textarea',
			title: __( 'Textarea Control', 'axellcore-atelier' ),
			description: __( 'A textarea input for multiple lines of text.', 'axellcore-atelier' ),
			attributes: { type: 'textarea' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'textarea' ),
		},
		{
			name: 'select',
			title: __( 'Select Control', 'axellcore-atelier' ),
			description: __( 'A dropdown with a fixed or dynamically-sourced list of options.', 'axellcore-atelier' ),
			attributes: { type: 'select' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'select' ),
		},
		{
			name: 'checkbox',
			title: __( 'Checkbox Control', 'axellcore-atelier' ),
			description: __( 'A simple checkbox input.', 'axellcore-atelier' ),
			attributes: { type: 'checkbox' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'checkbox' ),
		},
		{
			name: 'hidden',
			title: __( 'Hidden Control', 'axellcore-atelier' ),
			description: __( 'A hidden input field.', 'axellcore-atelier' ),
			icon: 'visibility',
			attributes: { type: 'hidden' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'hidden' ),
		},
	];

	VARIATIONS.forEach( function ( variation ) {
		blocks.registerBlockVariation( 'axell/form-control', variation );
	} );
} )( window.wp.blocks, window.wp.i18n );
