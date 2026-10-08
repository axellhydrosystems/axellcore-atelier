<?php
/**
 * Member values as stored and as shown: the phone with +55 and digits only,
 * the CEP and the CPF/CNPJ without their masks (the CNPJ may have letters),
 * and the masks put back for display.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store and display formats of member values.
 */
final class Format {

	/**
	 * Only the digits of a value.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function digits( $value ) {
		return (string) preg_replace( '/\D+/', '', (string) $value );
	}

	/**
	 * A Brazilian phone's national digits (area code and number), without
	 * the country code a stored or pasted value may have.
	 *
	 * @param string $value Phone, masked or stored ("+5511987654321").
	 * @return string
	 */
	public static function phone_national( $value ) {
		$digits = self::digits( $value );
		if ( strlen( $digits ) > 11 && 0 === strpos( $digits, '55' ) ) {
			$digits = substr( $digits, 2 );
		}
		return $digits;
	}

	/**
	 * A Brazilian phone as stored: +55 and the digits ('' for none).
	 *
	 * @param string $value Phone as typed.
	 * @return string
	 */
	public static function phone_store( $value ) {
		$digits = self::phone_national( $value );
		return '' === $digits ? '' : '+55' . $digits;
	}

	/**
	 * A Brazilian phone as shown: (11) 98765-4321 or (11) 3456-7890.
	 *
	 * @param string $value Stored phone.
	 * @return string
	 */
	public static function phone( $value ) {
		$digits = self::phone_national( $value );
		if ( preg_match( '/^(\d{2})(\d{4,5})(\d{4})$/', $digits, $parts ) ) {
			return sprintf( '(%s) %s-%s', $parts[1], $parts[2], $parts[3] );
		}
		return (string) $value;
	}

	/**
	 * A site address as stored: https:// added when it came without a
	 * scheme, then checked. '' for none; null when it is not a valid
	 * address (http or https, a host with a dot).
	 *
	 * @param string $value Address as typed ("www.site.com.br/portfolio").
	 * @return string|null
	 */
	public static function url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $value ) ) {
			$value = 'https://' . ltrim( $value, '/' );
		}
		$url    = esc_url_raw( $value, array( 'http', 'https' ) );
		$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = (string) wp_parse_url( $url, PHP_URL_HOST );
		$valid  = '' !== $url
			&& in_array( strtolower( $scheme ), array( 'http', 'https' ), true )
			&& false !== filter_var( $url, FILTER_VALIDATE_URL )
			&& preg_match( '/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/i', $host );
		return $valid ? $url : null;
	}

	/**
	 * A CEP as shown: 01001-000.
	 *
	 * @param string $value Stored CEP.
	 * @return string
	 */
	public static function postcode( $value ) {
		$digits = self::digits( $value );
		return 8 === strlen( $digits ) ? substr( $digits, 0, 5 ) . '-' . substr( $digits, 5 ) : (string) $value;
	}

	/**
	 * A CPF or CNPJ as shown: 000.000.000-00, or 00.000.000/0000-00 with
	 * the letters an alphanumeric CNPJ may have.
	 *
	 * @param string $value Stored document.
	 * @return string
	 */
	public static function document( $value ) {
		$document = Document::normalize( $value );
		if ( preg_match( '/^(\d{3})(\d{3})(\d{3})(\d{2})$/', $document, $parts ) ) {
			return sprintf( '%s.%s.%s-%s', $parts[1], $parts[2], $parts[3], $parts[4] );
		}
		if ( preg_match( '/^([0-9A-Z]{2})([0-9A-Z]{3})([0-9A-Z]{3})([0-9A-Z]{4})(\d{2})$/', $document, $parts ) ) {
			return sprintf( '%s.%s.%s/%s-%s', $parts[1], $parts[2], $parts[3], $parts[4], $parts[5] );
		}
		return (string) $value;
	}
}
