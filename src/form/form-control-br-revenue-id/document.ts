/**
 * Brazilian tax ids: CPF (person) and CNPJ (legal entity, including the
 * alphanumeric CNPJ from 2026). Masking and check-digit validation, shared by
 * the document store (view.ts).
 */
export type DocType = 'cpf' | 'cnpj' | '';

const digitsOnly = ( v: string ) => v.replace( /\D/g, '' );

/** Upper-case letters and digits (the first 12 CNPJ characters may be letters). */
const alnumUpper = ( v: string ) => v.toUpperCase().replace( /[^0-9A-Z]/g, '' );

/** Type of a typed value when no type is chosen: more than 11 characters is a CNPJ. */
export const guessType = ( v: string ): DocType => ( alnumUpper( v ).length > 11 ? 'cnpj' : 'cpf' );

export function mask( value: string, type: DocType ): string {
	if ( type === 'cnpj' ) {
		const raw = alnumUpper( value ).slice( 0, 14 );
		return ( raw.slice( 0, 12 ) + digitsOnly( raw.slice( 12 ) ).slice( 0, 2 ) )
			.replace( /^([0-9A-Z]{2})([0-9A-Z])/, '$1.$2' )
			.replace( /^([0-9A-Z]{2})\.([0-9A-Z]{3})([0-9A-Z])/, '$1.$2.$3' )
			.replace( /\.([0-9A-Z]{3})([0-9A-Z])/, '.$1/$2' )
			.replace( /([0-9A-Z]{4})(\d)/, '$1-$2' );
	}
	return digitsOnly( value )
		.slice( 0, 11 )
		.replace( /(\d{3})(\d)/, '$1.$2' )
		.replace( /(\d{3})(\d)/, '$1.$2' )
		.replace( /(\d{3})(\d{1,2})$/, '$1-$2' );
}

/** Mod-11 check digit shared by CPF and CNPJ. */
function checkDigit( values: number[], weights: number[] ): number {
	const sum = values.reduce( ( total, v, i ) => total + v * weights[ i ], 0 );
	const mod = sum % 11;
	return mod < 2 ? 0 : 11 - mod;
}

export function isValidCPF( v: string ): boolean {
	const d = digitsOnly( v );
	if ( d.length !== 11 || /^(\d)\1{10}$/.test( d ) ) {
		return false;
	}
	const nums = d.split( '' ).map( Number );
	const dv1 = checkDigit( nums.slice( 0, 9 ), [ 10, 9, 8, 7, 6, 5, 4, 3, 2 ] );
	const dv2 = checkDigit( [ ...nums.slice( 0, 9 ), dv1 ], [ 11, 10, 9, 8, 7, 6, 5, 4, 3, 2 ] );
	return nums[ 9 ] === dv1 && nums[ 10 ] === dv2;
}

export function isValidCNPJ( v: string ): boolean {
	const d = alnumUpper( v );
	if ( ! /^[0-9A-Z]{12}\d{2}$/.test( d ) || /^(.)\1{13}$/.test( d ) ) {
		return false;
	}
	// Character value: charCode - 48 (digits 0-9, letters 17-42), Receita Federal.
	const values = d.split( '' ).map( ( c ) => c.charCodeAt( 0 ) - 48 );
	const dv1 = checkDigit( values.slice( 0, 12 ), [ 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ] );
	const dv2 = checkDigit( [ ...values.slice( 0, 12 ), dv1 ], [ 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ] );
	return values[ 12 ] === dv1 && values[ 13 ] === dv2;
}

export const PLACEHOLDERS: Record< string, string > = {
	cpf: '000.000.000-00',
	cnpj: '00.000.000/0000-00',
	'': '000.000.000-00 / 00.000.000/0000-00',
};
