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
	 * Calls to Google per address in RATE_WINDOW (cached answers do not count).
	 */
	const RATE_LIMIT = 100;

	/**
	 * The rate limit's window, in seconds.
	 */
	const RATE_WINDOW = 600;

	/**
	 * How long an answer is kept, in seconds (a day).
	 */
	const CACHE_TTL = 86400;

	/**
	 * The view module (src/places/view.ts) and its stylesheet.
	 */
	const HANDLE = 'axellcore-atelierclub-places';

	/**
	 * The street field that searches, and the fields an address fills in.
	 */
	const FORM_FIELDS = array(
		'street'       => 'address_street',
		'number'       => 'address_number',
		'complement'   => 'address_2',
		'neighborhood' => 'neighborhood',
		'state'        => 'state',
		'city'         => 'city',
		'postal'       => 'postal',
	);

	/**
	 * Google Maps' logo, from Google's attribution assets, unmodified: white
	 * for a dark list, dark gray for a light one (98×18).
	 */
	const LOGOS = array(
		'dark'  => 'assets/places/GoogleMaps_Logo_White.svg',
		'light' => 'assets/places/GoogleMaps_Logo_DarkGray.svg',
	);

	/**
	 * The admin-post action of "Clear address cache".
	 */
	const CLEAR_ACTION = 'axellcore_atelierclub_places_clear';

	/**
	 * Prefixes of the cached answers (suggestions, addresses), after
	 * "_transient_" in the options table.
	 */
	const CACHE_PREFIXES = array( 'axell_places_s_', 'axell_places_d_' );

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
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( $this, 'handle_clear_cache' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
		add_action( 'init', array( $this, 'register_assets' ) );
		add_filter( 'render_block_axell/form-control', array( $this, 'decorate_street' ), 20 );
	}

	/**
	 * The view module and its stylesheet, enqueued by the street field.
	 */
	public function register_assets() {
		wp_register_script_module(
			self::HANDLE,
			AXELLCORE_ATELIERCLUB_URL . 'build/places/view.js',
			array( array( 'id' => '@wordpress/interactivity' ) ),
			AXELLCORE_ATELIERCLUB_VERSION
		);
		wp_register_style( self::HANDLE, AXELLCORE_ATELIERCLUB_URL . 'build/places/style-frontend.css', array(), AXELLCORE_ATELIERCLUB_VERSION );
	}

	/**
	 * The street field of a form, as a search: Google's suggestions below it
	 * (with Google Maps' attribution, required without a map), and choosing
	 * one fills in the address fields (src/places/view.ts).
	 *
	 * @param string $content Block HTML.
	 * @return string
	 */
	public function decorate_street( $content ) {
		if ( is_admin() || ! self::active() ) {
			return $content;
		}
		$p = new \WP_HTML_Tag_Processor( $content );
		if ( ! $p->next_tag( array( 'tag_name' => 'INPUT' ) ) || self::FORM_FIELDS['street'] !== $p->get_attribute( 'name' ) ) {
			return $content;
		}
		return self::search_field( $content, self::FORM_FIELDS );
	}

	/**
	 * A street field as a search over Google's addresses: the first input of
	 * the HTML becomes a combobox, with the list and Google Maps' logo under
	 * it; choosing an address fills in the fields named in $fields (the
	 * form's, or the profile's). Unchanged when the suggestions are off.
	 *
	 * @param string               $html       HTML with the street input.
	 * @param array<string,string> $fields     Field names by role (FORM_FIELDS' keys).
	 * @param string               $background The list's background: dark or light.
	 * @param string               $modifier   An extra class of the wrapper.
	 * @return string
	 */
	public static function search_field( $html, array $fields, $background = 'dark', $modifier = '' ) {
		if ( ! self::active() ) {
			return $html;
		}
		$p = new \WP_HTML_Tag_Processor( $html );
		if ( ! $p->next_tag( array( 'tag_name' => 'INPUT' ) ) ) {
			return $html;
		}

		$list = (string) ( $fields['street'] ?? 'street' ) . '-places';
		foreach ( array(
			'role'                        => 'combobox',
			// A combobox: no browser autofill over the suggestions.
			'autocomplete'                => Form_Directives::NO_AUTOFILL,
			'aria-autocomplete'           => 'list',
			'aria-controls'               => $list,
			'aria-expanded'               => 'false',
			'data-wp-bind--aria-expanded' => 'state.isOpen',
			// The value lives in the context: a value set by the server
			// (the profile's) would come back on every render.
			'data-wp-bind--value'         => 'context.value',
			'data-wp-on--input'           => 'actions.search',
			'data-wp-on--keydown'         => 'actions.keydown',
		) as $name => $value ) {
			$p->set_attribute( $name, $value );
		}

		wp_enqueue_script_module( self::HANDLE );
		wp_enqueue_style( self::HANDLE );
		wp_interactivity_state( 'axell/places', self::client_state() );

		$context = array(
			'fields'  => $fields,
			'value'   => (string) $p->get_attribute( 'value' ),
			'items'   => array(),
			'open'    => false,
			'active'  => -1,
			'typed'   => '',
			'loading' => false,
		);
		return sprintf(
			'<div class="aa-places-search%5$s" data-wp-interactive="axell/places" data-wp-context="%1$s" data-wp-on--focusout="actions.close">%2$s<p class="aa-places-popup aa-places-status" role="status" hidden data-wp-bind--hidden="!state.hasStatus" data-wp-text="state.statusText"></p><div class="aa-places-popup" hidden data-wp-bind--hidden="!state.isOpen"><ul id="%3$s" role="listbox" tabindex="-1" data-wp-on--click="actions.pick" data-wp-on--mousedown="actions.keepFocus" data-wp-watch="callbacks.render"></ul><p class="aa-places-attribution">%4$s</p></div></div>',
			esc_attr( (string) wp_json_encode( $context ) ),
			trim( $p->get_updated_html() ),
			esc_attr( $list ),
			self::attribution( $background ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
			'' !== $modifier ? ' ' . esc_attr( $modifier ) : ''
		);
	}


	/**
	 * Google Maps' attribution, required when its suggestions show without a
	 * map: its logo, as Google gives it (no change), labelled "Google Maps",
	 * at the least height Google allows (16px of 16 to 19).
	 *
	 * @param string $background The list's background: dark or light.
	 * @return string
	 */
	public static function attribution( $background = 'dark' ) {
		/**
		 * The background the address list shows the logo on.
		 *
		 * @param string $background dark (the Atelier) or light.
		 */
		$background = (string) apply_filters( 'axellcore_atelierclub_places_logo', $background );
		$file       = self::LOGOS[ $background ] ?? self::LOGOS['dark'];
		return sprintf(
			'<img class="aa-places-logo" src="%s" alt="Google Maps" width="87" height="16" translate="no" decoding="async">',
			esc_url( AXELLCORE_ATELIERCLUB_URL . $file )
		);
	}

	/**
	 * What the view module needs: the routes and the shortest input.
	 *
	 * @return array<string,mixed>
	 */
	public static function client_state() {
		return array(
			'autocompleteUrl' => rest_url( Rest::NAMESPACE . '/places/autocomplete' ),
			'detailsUrl'      => rest_url( Rest::NAMESPACE . '/places/details' ),
			'minInput'        => self::MIN_INPUT,
			'searching'       => __( 'Searching addresses…', 'axellcore-atelierclub' ),
			'notFound'        => __( 'No address found.', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * The address bar drops the "cleared" notice's argument once shown.
	 *
	 * @param string[] $args Arguments WordPress removes.
	 * @return string[]
	 */
	public function removable_query_args( $args ) {
		$args[] = 'aa-places-cleared';
		return $args;
	}

	/**
	 * The cached answers' option names (their timeouts follow them).
	 *
	 * @return string[]
	 */
	private static function cached_options() {
		global $wpdb;
		$names = array();
		foreach ( self::CACHE_PREFIXES as $prefix ) {
			$like  = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
			$names = array_merge( $names, (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- transients by prefix; there is no API for that.
		}
		return $names;
	}

	/**
	 * How many answers are cached.
	 *
	 * @return int
	 */
	public static function cache_count() {
		return count( self::cached_options() );
	}

	/**
	 * Delete the cached answers; the rate limit counts stay.
	 *
	 * @return int How many were deleted.
	 */
	public static function clear_cache() {
		$names = self::cached_options();
		foreach ( $names as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		}
		return count( $names );
	}

	/**
	 * The address of "Clear address cache" (nonce included).
	 *
	 * @return string
	 */
	public static function clear_cache_url() {
		return wp_nonce_url( add_query_arg( 'action', self::CLEAR_ACTION, admin_url( 'admin-post.php' ) ), self::CLEAR_ACTION );
	}

	/**
	 * Admin-post: clear the cache, back to the Integrations tab with the count.
	 */
	public function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'axellcore-atelierclub' ), 403 );
		}
		check_admin_referer( self::CLEAR_ACTION );
		$deleted = self::clear_cache();
		wp_safe_redirect( add_query_arg( 'aa-places-cleared', $deleted, Settings::url( 'integrations' ) ) );
		exit;
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
		return rest_ensure_response( self::suggestions( (string) $request['input'], (string) $request['session'], Members::client_ip() ) );
	}

	/**
	 * GET /places/details — the chosen address, split into the form's fields.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_details( \WP_REST_Request $request ) {
		$address = self::address( (string) $request['id'], (string) $request['session'], Members::client_ip() );
		if ( null === $address ) {
			return new \WP_Error( 'aa_place_not_found', __( 'Address not found.', 'axellcore-atelierclub' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $address );
	}

	/**
	 * Google's suggestions for an input, in Brazil: [ { id, main, secondary } ].
	 * Empty when off, for a short input, over the rate limit or when Google
	 * fails.
	 *
	 * @param string      $input   What was typed.
	 * @param string      $session Session token (one per search, ended by details).
	 * @param string|null $ip      Client address, for the rate limit (none: not limited).
	 * @return array<int,array{id:string,main:string,secondary:string}>
	 */
	public static function suggestions( $input, $session = '', $ip = null ) {
		$input = trim( preg_replace( '/\s+/', ' ', (string) $input ) );
		if ( ! self::active() || self::length( $input ) < self::MIN_INPUT ) {
			return array();
		}

		$cache = 'axell_places_s_' . md5( self::lower( $input ) );
		$found = get_transient( $cache );
		if ( is_array( $found ) ) {
			return $found;
		}

		if ( null !== $ip && is_wp_error( self::check_rate_limit( $ip ) ) ) {
			return array();
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
	 * A place's address in the form's fields, or null when off, not found or
	 * over the rate limit.
	 *
	 * @param string      $id      Google's place id.
	 * @param string      $session Session token of the search that offered it.
	 * @param string|null $ip      Client address, for the rate limit (none: not limited).
	 * @return array<string,string>|null
	 */
	public static function address( $id, $session = '', $ip = null ) {
		$id = (string) $id;
		if ( ! self::active() || ! preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
			return null;
		}

		$cache = 'axell_places_d_' . md5( $id );
		$found = get_transient( $cache );
		if ( is_array( $found ) ) {
			return $found;
		}

		if ( null !== $ip && is_wp_error( self::check_rate_limit( $ip ) ) ) {
			return null;
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
	 * complement (an apartment or room Google knows, "ap 103"), neighborhood,
	 * city, state (UF) and CEP (8 digits, or empty when Google has only its
	 * prefix).
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
			'address_2'      => $part( array( 'subpremise' ) ),
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
