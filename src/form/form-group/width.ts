/**
 * Widths of the Largura slider, in 1/12 steps. The slider value is the index
 * in this list plus one (0 means automatic).
 */
export const WIDTHS = [
	'8.33333333%',
	'16.6666667%',
	'25%',
	'33.3333333%',
	'41.6666667%',
	'50%',
	'58.3333333%',
	'66.6666667%',
	'75%',
	'83.3333333%',
	'91.6666667%',
	'100%',
];

export const DEFAULT_UNIT = '%';

export const WIDTH_UNITS = [
	{ value: '%', label: '%', default: 100 },
	{ value: 'px', label: 'px', default: 320 },
	{ value: 'em', label: 'em', default: 20 },
	{ value: 'rem', label: 'rem', default: 20 },
	{ value: 'vw', label: 'vw', default: 50 },
];

/**
 * A number typed without a unit gets the default unit (%).
 * @param value
 */
export function withDefaultUnit( value?: string ): string {
	if ( ! value ) {
		return '';
	}
	return /^-?\d*\.?\d+$/.test( value.trim() ) ? `${ value.trim() }${ DEFAULT_UNIT }` : value;
}

/**
 * Inline style for the chosen width, shared by edit() and save(). Empty means
 * the block keeps its automatic width.
 * @param fieldWidth
 */
export function widthStyle( fieldWidth: string ): { width: string } | undefined {
	return fieldWidth ? { width: fieldWidth } : undefined;
}
