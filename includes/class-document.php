<?php
/**
 * Brazilian tax ids on the server: CPF (person) and CNPJ (legal entity,
 * including the alphanumeric CNPJ from 2026). The same rules as the form's
 * src/form/form-control-br-revenue-id/document.ts.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalization and check-digit validation of CPF and CNPJ.
 */
final class Document {

	/**
	 * Document type of a profile type value: `individual` / `cpf` is a CPF,
	 * `legal_entity` / `cnpj` a CNPJ, anything else '' (by length).
	 *
	 * @param string $profile_type Profile type field value.
	 * @return string 'cpf', 'cnpj' or ''.
	 */
	public static function type_of( $profile_type ) {
		$value = strtolower( trim( (string) $profile_type ) );
		if ( 'individual' === $value || 'cpf' === $value ) {
			return 'cpf';
		}
		return ( 'legal_entity' === $value || 'cnpj' === $value ) ? 'cnpj' : '';
	}

	/**
	 * The stored form of a document: upper-case letters and digits (a CPF is
	 * digits only), without the mask.
	 *
	 * @param string $value Typed value.
	 * @return string
	 */
	public static function normalize( $value ) {
		return (string) preg_replace( '/[^0-9A-Z]/', '', strtoupper( (string) $value ) );
	}

	/**
	 * Whether a value is a valid document of the type (by length when '').
	 *
	 * @param string $value Typed or normalized value.
	 * @param string $type  'cpf', 'cnpj' or ''.
	 * @return bool
	 */
	public static function is_valid( $value, $type = '' ) {
		$value = self::normalize( $value );
		if ( '' === $type ) {
			$type = strlen( $value ) > 11 ? 'cnpj' : 'cpf';
		}
		return 'cnpj' === $type ? self::is_valid_cnpj( $value ) : self::is_valid_cpf( $value );
	}

	/**
	 * CPF: 11 digits, not all the same, two mod-11 check digits.
	 *
	 * @param string $value Normalized value.
	 * @return bool
	 */
	public static function is_valid_cpf( $value ) {
		if ( ! preg_match( '/^\d{11}$/', $value ) || preg_match( '/^(\d)\1{10}$/', $value ) ) {
			return false;
		}
		$nums = array_map( 'intval', str_split( $value ) );
		$dv1  = self::check_digit( array_slice( $nums, 0, 9 ), array( 10, 9, 8, 7, 6, 5, 4, 3, 2 ) );
		$dv2  = self::check_digit( array_merge( array_slice( $nums, 0, 9 ), array( $dv1 ) ), array( 11, 10, 9, 8, 7, 6, 5, 4, 3, 2 ) );
		return $nums[9] === $dv1 && $nums[10] === $dv2;
	}

	/**
	 * CNPJ: 12 letters or digits and 2 check digits, not all the same. Each
	 * character is worth its code minus 48 (Receita Federal's rule for the
	 * alphanumeric CNPJ; digits keep their value).
	 *
	 * @param string $value Normalized value.
	 * @return bool
	 */
	public static function is_valid_cnpj( $value ) {
		if ( ! preg_match( '/^[0-9A-Z]{12}\d{2}$/', $value ) || preg_match( '/^(.)\1{13}$/', $value ) ) {
			return false;
		}
		$values = array_map(
			static function ( $c ) {
				return ord( $c ) - 48;
			},
			str_split( $value )
		);
		$dv1    = self::check_digit( array_slice( $values, 0, 12 ), array( 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ) );
		$dv2    = self::check_digit( array_merge( array_slice( $values, 0, 12 ), array( $dv1 ) ), array( 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ) );
		return $values[12] === $dv1 && $values[13] === $dv2;
	}

	/**
	 * Mod-11 check digit shared by CPF and CNPJ.
	 *
	 * @param int[] $values  Values.
	 * @param int[] $weights Weights.
	 * @return int
	 */
	private static function check_digit( array $values, array $weights ) {
		$sum = 0;
		foreach ( $values as $i => $v ) {
			$sum += $v * $weights[ $i ];
		}
		$mod = $sum % 11;
		return $mod < 2 ? 0 : 11 - $mod;
	}
}
