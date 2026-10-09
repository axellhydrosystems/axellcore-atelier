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
			title: __( 'Form Control (text)', 'axellcore-atelier' ),
			description: __( 'A generic text input.', 'axellcore-atelier' ),
			attributes: { type: 'text' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'text' ),
		},
		{
			name: 'email',
			title: __( 'Form Control (email)', 'axellcore-atelier' ),
			description: __(
				'Used for email addresses.',
				'axellcore-atelier'
			),
			attributes: { type: 'email' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'email' ),
		},
		{
			name: 'url',
			title: __( 'Form Control (url)', 'axellcore-atelier' ),
			description: __( 'Used for URLs.', 'axellcore-atelier' ),
			attributes: { type: 'url' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'url' ),
		},
		{
			name: 'tel',
			title: __( 'Form Control (tel)', 'axellcore-atelier' ),
			description: __(
				'Used for phone numbers.',
				'axellcore-atelier'
			),
			attributes: { type: 'tel' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'tel' ),
		},
		{
			name: 'number',
			title: __( 'Form Control (number)', 'axellcore-atelier' ),
			description: __( 'A numeric input.', 'axellcore-atelier' ),
			attributes: { type: 'number' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'number' ),
		},
		{
			name: 'textarea',
			title: __( 'Form Control (textarea)', 'axellcore-atelier' ),
			description: __(
				'A textarea input for multiple lines of text.',
				'axellcore-atelier'
			),
			attributes: { type: 'textarea' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'textarea' ),
		},
		{
			name: 'select',
			title: __( 'Form Control (select)', 'axellcore-atelier' ),
			description: __(
				'A dropdown with a fixed or dynamically-sourced list of options.',
				'axellcore-atelier'
			),
			attributes: { type: 'select' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'select' ),
		},
		{
			name: 'hidden',
			title: __( 'Form Control (hidden)', 'axellcore-atelier' ),
			description: __( 'A hidden input field.', 'axellcore-atelier' ),
			icon: 'visibility',
			attributes: { type: 'hidden' },
			isDefault: true,
			scope: [ 'inserter', 'transform' ],
			isActive: isType( 'hidden' ),
		},
	];

VARIATIONS.forEach( ( variation ) => {
	registerBlockVariation( 'axell/form-control', variation );
} );
