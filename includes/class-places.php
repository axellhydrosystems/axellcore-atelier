<?php
/**
 * Google's address suggestions (Places API New), through this site: the
 * street field asks /places/autocomplete while it is typed, and the address
 * chosen comes from /places/details already split into the form's fields.
 * The API key (Atelier → Settings → Integrations) never reaches the browser.
 *
 * Both routes are public, as the application form is, and each call to
 * Google is charged: short inputs are ignored, answers are cached and every
 * address is rate limited.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Address autocomplete with the Places API (New).
 */
final class Places {

	/**
	 * Google's suggestions.
	 */
	const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

	/**
	 * Google's place details (its id appended).
	 */
	const DETAILS_URL = 'https://places.googleapis.com/v1/places/';

	/**
	 * The shortest input sent to Google.
	 */
	const MIN_INPUT = 4;

	/**
	 * Calls to these routes per address in RATE_WINDOW.
	 */
	const RATE_LIMIT = 60;

	/**
	 * The rate limit's window, in seconds.
	 */
	const RATE_WINDOW = 600;

	/**
	 * How long an answer is kept, in seconds (a day).
	 */
	const CACHE_TTL = 86400;

	/**
	 * Singleton instance.
	 *
	 * @var Places|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Places
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor; use instance().
	 */
	private function __construct() {}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Whether the suggestions are on and have a key.
	 *
	 * @return bool
	 */
	public static function active() {
		return (bool) Settings::get( 'places_enabled' ) && '' !== self::api_key();
	}

	/**
	 * The API key.
	 *
	 * @return string
	 */
	private static function api_key() {
		return trim( (string) Settings::get( 'places_api_key' ) );
	}

	/**
	 * The two routes, public as the form that uses them.
	 */
	public function register_routes() {
		$session = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_key',
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/places/autocomplete',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_autocomplete' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'input'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session' => $session,
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/places/details',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_details' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'session' => $session,
				),
			)
		);
	}

	/**
	 * GET /places/autocomplete — the suggestions for what was typed.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_autocomplete( \WP_REST_Request $request ) {
		$limited = self::check_rate_limit( Members::client_ip() );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		return rest_ensure_response( self::suggestions( (string) $request['input'], (string) $request['session'] ) );
	}

	/**
	 * GET /places/details — the chosen address, split into the form's fields.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_details( \WP_REST_Request $request ) {
		$limited = self::check_rate_limit( Members::client_ip() );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}
		$address = self::address( (string) $request['id'], (string) $request['session'] );
		if ( null === $address ) {
			return new \WP_Error( 'aa_place_not_found', __( 'Address not found.', 'axellcore-atelierclub' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $address );
	}

	/**
	 * Google's suggestions for an input, in Brazil: [ { id, main, secondary } ].
	 * Empty when off, for a short input or when Google fails.
	 *
	 * @param string $input   What was typed.
	 * @param string $session Session token (one per search, ended by details).
	 * @return array<int,array{id:string,main:string,secondary:string}>
	 */
	public static function suggestions( $input, $session = '' ) {
		$input = trim( preg_replace( '/\s+/', ' ', (string) $input ) );
		if ( ! self::active() || self::length( $input ) < self::MIN_INPUT ) {
			return array();
		}

		$cache = 'axell_places_s_' . md5( self::lower( $input ) );
		$found = get_transient( $cache );
		if ( is_array( $found ) ) {
			return $found;
		}

		$body = array(
			'input'                => $input,
			'includedRegionCodes'  => array( 'br' ),
			'languageCode'         => 'pt-BR',
			'includedPrimaryTypes' => array( 'street_address', 'route', 'premise' ),
		);
		if ( '' !== $session ) {
			$body['sessionToken'] = $session;
		}
		$result = self::request(
			wp_remote_post(
				self::AUTOCOMPLETE_URL,
				array(
					'timeout' => 8,
					'headers' => array(
						'Content-Type'   => 'application/json',
						'X-Goog-Api-Key' => self::api_key(),
					),
					'body'    => (string) wp_json_encode( $body ),
				)
			)
		);
		if ( null === $result ) {
			return array();
		}

		$items = array();
		foreach ( (array) ( $result['suggestions'] ?? array() ) as $suggestion ) {
			$place = $suggestion['placePrediction'] ?? null;
			if ( ! is_array( $place ) || empty( $place['placeId'] ) ) {
				continue;
			}
			$items[] = array(
				'id'        => (string) $place['placeId'],
				'main'      => (string) ( $place['structuredFormat']['mainText']['text'] ?? $place['text']['text'] ?? '' ),
				'secondary' => (string) ( $place['structuredFormat']['secondaryText']['text'] ?? '' ),
			);
		}
		set_transient( $cache, $items, self::CACHE_TTL );
		return $items;
	}

	/**
	 * A place's address in the form's fields, or null when off or not found.
	 *
	 * @param string $id      Google's place id.
	 * @param string $session Session token of the search that offered it.
	 * @return array<string,string>|null
	 */
	public static function address( $id, $session = '' ) {
		$id = (string) $id;
		if ( ! self::active() || ! preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
			return null;
		}

		$cache = 'axell_places_d_' . md5( $id );
		$found = get_transient( $cache );
		if ( is_array( $found ) ) {
			return $found;
		}

		$args = array( 'languageCode' => 'pt-BR' );
		if ( '' !== $session ) {
			$args['sessionToken'] = $session;
		}
		$result = self::request(
			wp_remote_get(
				add_query_arg( $args, self::DETAILS_URL . $id ),
				array(
					'timeout' => 8,
					'headers' => array(
						'X-Goog-Api-Key'   => self::api_key(),
						'X-Goog-FieldMask' => 'addressComponents',
					),
				)
			)
		);
		if ( null === $result || empty( $result['addressComponents'] ) ) {
			return null;
		}

		$address = self::fields( (array) $result['addressComponents'] );
		set_transient( $cache, $address, self::CACHE_TTL );
		return $address;
	}

	/**
	 * Google's address components as the form's fields: street, number,
	 * neighborhood, city, state (UF) and CEP (8 digits, or empty when Google
	 * has only its prefix).
	 *
	 * @param array $components addressComponents ({ longText, shortText, types }).
	 * @return array<string,string>
	 */
	public static function fields( array $components ) {
		$part = static function ( array $types, $short = false ) use ( $components ) {
			foreach ( $types as $type ) {
				foreach ( $components as $component ) {
					if ( in_array( $type, (array) ( $component['types'] ?? array() ), true ) ) {
						return trim( (string) ( $component[ $short ? 'shortText' : 'longText' ] ?? '' ) );
					}
				}
			}
			return '';
		};

		$postal = Format::digits( $part( array( 'postal_code' ) ) );
		$state  = strtoupper( $part( array( 'administrative_area_level_1' ), true ) );

		return array(
			'address_street' => $part( array( 'route' ) ),
			'address_number' => $part( array( 'street_number' ) ),
			'neighborhood'   => $part( array( 'sublocality_level_1', 'sublocality', 'neighborhood' ) ),
			'city'           => $part( array( 'administrative_area_level_2', 'locality' ) ),
			'state'          => 2 === strlen( $state ) ? $state : '',
			'postal'         => 8 === strlen( $postal ) ? $postal : '',
		);
	}

	/**
	 * Google's JSON answer, or null on any failure.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return array|null
	 */
	private static function request( $response ) {
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$result = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Calls per address: RATE_LIMIT in RATE_WINDOW.
	 *
	 * @param string $ip Client address.
	 * @return true|\WP_Error
	 */
	public static function check_rate_limit( $ip ) {
		$key   = 'axell_places_' . md5( (string) $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return new \WP_Error( 'aa_rate_limited', __( 'Too many attempts. Try again in a few minutes.', 'axellcore-atelierclub' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	/**
	 * A text's length in characters.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	/**
	 * A text in lower case (the cache key ignores case).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
