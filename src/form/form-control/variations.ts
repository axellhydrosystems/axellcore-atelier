/**
 * Per-type inserter variations for axell/form-control — picking a control
 * type (Text/Email/URL/Phone/Number/Textarea/Select/Checkbox/Hidden) at
 * insertion time, instead of only via the Inspector's Type dropdown
 * (edit.tsx) after the fact. Ported verbatim from
 * axellcore/form-input/variations.js (which itself mirrors Gutenberg's
 * removed experimental core/form-input block — see this plugin's CLAUDE.md).
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import type { BlockVariation } from '@wordpress/blocks';
import type { FormControlAttributes, FormControlType } from './types';
import controlIcon from './icon';

function isType( type: FormControlType ) {
	return ( blockAttributes: Partial< FormControlAttributes > ) => {
		const current = blockAttributes && blockAttributes.type;
		return current === type || ( ! current && type === 'text' );
	};
}

const VARIATIONS: Array< BlockVariation< Partial< FormControlAttributes > > > =
	[
		{
			name: 'text',
			title: __( 'Form Control (text)', 'axellcore-atelierclub' ),
			description: __( 'A generic text input.', 'axellcore-atelierclub' ),
			attributes: { type: 'text' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'text' ),
		},
		{
			name: 'email',
			title: __( 'Form Control (email)', 'axellcore-atelierclub' ),
			description: __(
				'Used for email addresses.',
				'axellcore-atelierclub'
			),
			attributes: { type: 'email' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'email' ),
		},
		{
			name: 'url',
			title: __( 'Form Control (url)', 'axellcore-atelierclub' ),
			description: __( 'Used for URLs.', 'axellcore-atelierclub' ),
			attributes: { type: 'url' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'url' ),
		},
		{
			name: 'tel',
			title: __( 'Form Control (tel)', 'axellcore-atelierclub' ),
			description: __(
				'Used for phone numbers.',
				'axellcore-atelierclub'
			),
			attributes: { type: 'tel' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'tel' ),
		},
		{
			name: 'number',
			title: __( 'Form Control (number)', 'axellcore-atelierclub' ),
			description: __( 'A numeric input.', 'axellcore-atelierclub' ),
			attributes: { type: 'number' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'number' ),
		},
		{
			name: 'textarea',
			title: __( 'Form Control (textarea)', 'axellcore-atelierclub' ),
			description: __(
				'A textarea input for multiple lines of text.',
				'axellcore-atelierclub'
			),
			attributes: { type: 'textarea' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'textarea' ),
		},
		{
			name: 'select',
			title: __( 'Form Control (select)', 'axellcore-atelierclub' ),
			description: __(
				'A dropdown with a fixed or dynamically-sourced list of options.',
				'axellcore-atelierclub'
			),
			attributes: { type: 'select' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'select' ),
		},
		{
			name: 'hidden',
			title: __( 'Form Control (hidden)', 'axellcore-atelierclub' ),
			description: __( 'A hidden input field.', 'axellcore-atelierclub' ),
			icon: 'visibility',
			attributes: { type: 'hidden' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'hidden' ),
		},
		{
			name: 'autocomplete',
			title: __( 'Form Control (autocomplete)', 'axellcore-atelierclub' ),
			description: __( 'A text field that suggests posts as you type.', 'axellcore-atelierclub' ),
			icon: controlIcon,
			attributes: { type: 'autocomplete' },
			isDefault: false,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'autocomplete' ),
		},
	];

VARIATIONS.forEach( ( variation ) => {
	registerBlockVariation( 'axell/form-control', variation );
} );
