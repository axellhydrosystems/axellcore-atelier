<?php
/**
 * Member application: validation, storage, anti-spam, and the two ways the
 * form can submit (REST with JavaScript, admin-post without it).
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates member users (role member_pending) from a form submission.
 */
final class Members {

	/**
	 * Store target of a form that creates members (the form block's
	 * `storePostType`; `aa_member`, the former post type, means the same).
	 */
	const STORE = 'member';

	/**
	 * E-mail local parts too generic to be a username (the domain is used).
	 *
	 * @var string[]
	 */
	const GENERIC_EMAIL_PREFIXES = array( 'sales', 'hello', 'mail', 'contact', 'info' );

	/**
	 * Admin-post action of the no-JavaScript submission.
	 */
	const ADMIN_ACTION = 'axellcore_member_submit';

	/**
	 * Honeypot field. Real users never see it; bots fill it in.
	 */
	const HONEYPOT_FIELD = 'website';

	/**
	 * Submissions allowed per client within RATE_WINDOW seconds.
	 */
	const RATE_LIMIT = 5;

	/**
	 * Rate-limit window, in seconds.
	 */
	const RATE_WINDOW = 600;

	/**
	 * Required fields for a member submission.
	 *
	 * @var string[]
	 */
	const REQUIRED_FIELDS = array(
		'fullname',
		'company',
		'email',
		'phone',
		'primary_focus',
		'profile_type',
		'br_revenue_id',
		'address_street',
		'address_number',
		'neighborhood',
		'city',
		'state',
		'postal',
		'consent',
	);

	/**
	 * Optional text fields, stored (sanitize_text_field) as user meta
	 * under the field name, named as the adesão form names them. `email` and `url`
	 * are handled separately (their own sanitizers); `state`/`city` by the
	 * location-resolution step, not stored as plain meta.
	 *
	 * @var string[]
	 */
	const TEXT_META_FIELDS = array(
		'company',
		'phone',
		'professional_registration',
		'primary_focus',
		'profile_type',
		'br_revenue_id',
		'country',
		'address_street',
		'address_number',
		'address_2',
		'neighborhood',
		'landmark',
		'postal',
	);

	/**
	 * Partner store slots. Each slot stores its ID (`resellerN`, empty for
	 * free text) and the text shown to the user (`resellerN_title`).
	 *
	 * @var string[]
	 */
	const RESELLER_FIELDS = array( 'reseller1', 'reseller2', 'reseller3', 'reseller4', 'reseller5' );

	/**
	 * Singleton instance.
	 *
	 * @var Members|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Members
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_post_nopriv_' . self::ADMIN_ACTION, array( $this, 'handle_form_post' ) );
		add_action( 'admin_post_' . self::ADMIN_ACTION, array( $this, 'handle_form_post' ) );

		// A form that stores into members uses the member validation and storage.
		add_filter(
			'axellcore_form_store_handler',
			static function ( $handler, $post_type ) {
				if ( self::STORE !== $post_type ) {
					return $handler;
				}
				return static function ( array $fields ) {
					$result = Members::instance()->create_from_params( $fields );
					return is_wp_error( $result ) ? $result : (int) $result['id'];
				};
			},
			10,
			2
		);
	}

	/**
	 * Submission from the REST endpoint (JavaScript path).
	 *
	 * @param array  $params Submitted fields.
	 * @param string $ip     Client address (for the rate limit).
	 * @return array|\WP_Error Array with the new member ID, or an error.
	 */
	public function submit( array $params, $ip ) {
		if ( ! empty( $params[ self::HONEYPOT_FIELD ] ) ) {
			// Pretend it worked, so the bot learns nothing.
			return array(
				'success' => true,
				'id'      => 0,
			);
		}

		$limited = $this->check_rate_limit( $ip );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		return $this->create_from_params( $params );
	}

	/**
	 * Validate and store one application.
	 *
	 * @param array $params Submitted fields.
	 * @return array|\WP_Error Array with the new member ID, or an error.
	 */
	public function create_from_params( array $params ) {
		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( empty( $params[ $field ] ) ) {
				return self::field_error(
					'aa_missing_field',
					/* translators: %s: form field name. */
					sprintf( __( 'Missing required field: %s', 'axellcore-atelierclub' ), $field ),
					$field
				);
			}
		}

		$email = sanitize_email( $params['email'] );
		if ( '' === $email || ! is_email( $email ) ) {
			return self::field_error( 'aa_invalid_email', __( 'Invalid email address.', 'axellcore-atelierclub' ), 'email' );
		}

		$country  = strtoupper( trim( (string) ( $params['country'] ?? '' ) ) );
		$location = self::location( '' !== $country ? $country : 'BR', (string) $params['state'], (string) $params['city'] );
		if ( is_wp_error( $location ) ) {
			return $location;
		}
		list( $uf, $city ) = $location;

		if ( '' === $country || 'BR' === $country ) {
			// The rules of the form's masks (form-address/view.ts): a mobile
			// has 9 after the area code, a landline starts with 2 to 5.
			if ( ! preg_match( '/^\d{2}(9\d{8}|[2-5]\d{7})$/', self::digits( (string) $params['phone'] ) ) ) {
				return self::field_error( 'aa_invalid_phone', __( 'Enter a phone number with area code.', 'axellcore-atelierclub' ), 'phone' );
			}
			if ( ! preg_match( '/^\d{8}$/', self::digits( (string) $params['postal'] ) ) ) {
				return self::field_error( 'aa_invalid_postal', __( 'Enter a CEP with 8 digits.', 'axellcore-atelierclub' ), 'postal' );
			}
		}

		$document = Document::normalize( (string) $params['br_revenue_id'] );
		if ( ! Document::is_valid( $document, Document::type_of( (string) $params['profile_type'] ) ) ) {
			return self::field_error( 'aa_invalid_document', __( 'Invalid CPF/CNPJ.', 'axellcore-atelierclub' ), 'br_revenue_id' );
		}

		foreach ( self::RESELLER_FIELDS as $field ) {
			$id = absint( $params[ $field ] ?? 0 );
			if ( $id && ( Resellers::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) ) {
				return self::field_error( 'aa_invalid_reseller', __( 'Choose a reseller from the list.', 'axellcore-atelierclub' ), $field . '_title' );
			}
		}

		if ( email_exists( $email ) ) {
			return self::field_error( 'aa_email_exists', __( 'This e-mail is already registered.', 'axellcore-atelierclub' ), 'email', 409 );
		}
		if ( self::document_exists( $document ) ) {
			return self::field_error( 'aa_document_exists', __( 'This CPF/CNPJ is already registered.', 'axellcore-atelierclub' ), 'br_revenue_id', 409 );
		}

		$fullname = sanitize_text_field( $params['fullname'] );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => self::username_for( $email ),
				'user_email'   => $email,
				// Never shown: the member sets a password when access is granted.
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => $fullname,
				'user_url'     => esc_url_raw( (string) ( $params['url'] ?? '' ) ),
				'nickname'     => $fullname,
				'role'         => Settings::get( 'pending_on_create' ) ? Member::ROLE_PENDING : Member::ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error( 'aa_insert_failed', __( 'Could not save your application.', 'axellcore-atelierclub' ), array( 'status' => 500 ) );
		}

		$meta = array(
			'state'         => $uf,
			'city'          => $city,
			'br_revenue_id' => $document,
		);
		foreach ( self::TEXT_META_FIELDS as $field ) {
			if ( 'br_revenue_id' !== $field && ! empty( $params[ $field ] ) ) {
				$meta[ $field ] = sanitize_text_field( $params[ $field ] );
			}
		}

		// The stores filled in, moved up to the first positions (store 1 and
		// store 3 are saved as reseller1 and reseller2).
		foreach ( self::submitted_resellers( $params ) as $index => $store ) {
			$field = self::RESELLER_FIELDS[ $index ];
			$id    = $store['id'];
			// Custom store: a text "Nome - UF Cidade" that matches becomes a
			// pending revenda (Reseller_Store), and its ID is kept.
			if ( 0 === $id ) {
				$id = (int) apply_filters( 'axellcore_atelierclub_reseller_text', 0, $store['title'] );
			}
			$meta[ $field ]            = $id > 0 ? (string) $id : '';
			$meta[ $field . '_title' ] = $store['title'];
		}

		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}

		return array(
			'success' => true,
			'id'      => (int) $user_id,
		);
	}

	/**
	 * State and city as stored. The state is a code (BR, US: upper case) or,
	 * for other countries, a name; either way 2 characters or more, any case.
	 * In Brazil the state must be a UF and the city one of its cities (the
	 * bundled IBGE list, case-insensitive), stored with its proper name.
	 *
	 * @param string $country Country code.
	 * @param string $state   Submitted state.
	 * @param string $city    Submitted city name.
	 * @return array{0:string,1:string}|\WP_Error
	 */
	public static function location( $country, $state, $city ) {
		$country = strtoupper( trim( sanitize_text_field( $country ) ) );
		$state   = trim( sanitize_text_field( $state ) );
		$city    = trim( sanitize_text_field( $city ) );
		$invalid = self::field_error( 'aa_invalid_location', __( 'Invalid state/city.', 'axellcore-atelierclub' ), 'city' );

		if ( mb_strlen( $state ) < 2 || '' === $city ) {
			return $invalid;
		}
		if ( in_array( $country, array( '', 'BR', 'US' ), true ) ) {
			$state = strtoupper( $state );
		}
		if ( '' === $country || 'BR' === $country ) {
			$cities = Locations::instance()->cities_for_state( $state );
			$match  = array_values(
				array_filter(
					$cities,
					static function ( $name ) use ( $city ) {
						return 0 === strcasecmp( $name, $city ) || mb_strtolower( $name ) === mb_strtolower( $city );
					}
				)
			);
			if ( ! $match ) {
				return $invalid;
			}
			$city = $match[0];
		}
		return array( $state, $city );
	}

	/**
	 * Whether a member user already has this CPF/CNPJ.
	 *
	 * @param string $document Normalized CPF/CNPJ (Document::normalize()).
	 * @param int    $exclude  User ID to ignore (the one being edited).
	 * @return bool
	 */
	public static function document_exists( $document, $exclude = 0 ) {
		$ids = get_users(
			array(
				'meta_key'   => 'br_revenue_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- uniqueness check on one key.
				'meta_value' => $document, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- uniqueness check on one key.
				'exclude'    => $exclude ? array( $exclude ) : array(),
				'number'     => 1,
				'fields'     => 'ID',
			)
		);
		return ! empty( $ids );
	}

	/**
	 * Username for a new member, as WooCommerce builds one from an e-mail
	 * (wc_create_new_customer_username()): the part before the @ (the domain
	 * for generic ones such as info@), sanitized and lower case; a blocked
	 * or empty name becomes member_NNNN, and a taken one gets -NNNN.
	 *
	 * @param string $email  E-mail address.
	 * @param string $suffix Suffix of a retry.
	 * @return string
	 */
	public static function username_for( $email, $suffix = '' ) {
		$parts = explode( '@', $email );
		$base  = $parts[0];
		if ( in_array( $base, self::GENERIC_EMAIL_PREFIXES, true ) && isset( $parts[1] ) ) {
			$base = $parts[1];
		}
		$username = strtolower( sanitize_user( $base, true ) );

		$illegal = array_map( 'strtolower', (array) apply_filters( 'illegal_user_logins', array() ) );
		if ( '' === $username || in_array( $username, $illegal, true ) ) {
			$username = 'member_' . zeroise( wp_rand( 0, 9999 ), 4 );
		}
		$username .= $suffix;

		if ( username_exists( $username ) ) {
			return self::username_for( $email, '-' . zeroise( wp_rand( 0, 9999 ), 4 ) );
		}
		return $username;
	}

	/**
	 * No-JavaScript submission (admin-post.php). Goes back to the page that
	 * sent the form, at its #adesao anchor, with the result in the query.
	 */
	public function handle_form_post() {
		$params = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public form, same trust boundary as the REST endpoint.

		$result = $this->submit( is_array( $params ) ? $params : array(), self::client_ip() );
		$status = is_wp_error( $result ) ? 'error' : 'success';

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = home_url( '/' );
		}

		wp_safe_redirect( add_query_arg( 'axell-form', $status, $back ) . '#adesao' );
		exit;
	}

	/**
	 * Count this submission against the client's window.
	 *
	 * @param string $ip Client address.
	 * @return true|\WP_Error
	 */
	public function check_rate_limit( $ip ) {
		$key   = 'axell_members_' . md5( (string) $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return new \WP_Error(
				'aa_rate_limited',
				__( 'Too many attempts. Try again in a few minutes.', 'axellcore-atelierclub' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	/**
	 * Client address. Behind a proxy this is the proxy's address, so the limit
	 * is per connection source; see the plan's risks.
	 *
	 * @return string
	 */
	public static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Only the digits of a value (phone, CEP).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function digits( $value ) {
		return (string) preg_replace( '/\D+/', '', $value );
	}

	/**
	 * A validation error that names the field it is about, so the form can
	 * mark it (with JavaScript) or show it (without, see Form_Block).
	 *
	 * @param string $code    Error code.
	 * @param string $message Message for the visitor.
	 * @param string $field   Form field name.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	public static function field_error( $code, $message, $field, $status = 400 ) {
		return new \WP_Error(
			$code,
			$message,
			array(
				'status' => $status,
				'field'  => $field,
			)
		);
	}

	/**
	 * Codes of the errors a visitor can fix, whose message is shown after a
	 * no-JavaScript submission (any other error shows the form's own text).
	 *
	 * @return array<string,string> Code => message.
	 */
	public static function visitor_messages() {
		return array(
			'aa_missing_field'    => __( 'Fill in the required fields.', 'axellcore-atelierclub' ),
			'aa_invalid_email'    => __( 'Invalid email address.', 'axellcore-atelierclub' ),
			'aa_invalid_location' => __( 'Invalid state/city.', 'axellcore-atelierclub' ),
			'aa_invalid_phone'    => __( 'Enter a phone number with area code.', 'axellcore-atelierclub' ),
			'aa_invalid_postal'   => __( 'Enter a CEP with 8 digits.', 'axellcore-atelierclub' ),
			'aa_invalid_document' => __( 'Invalid CPF/CNPJ.', 'axellcore-atelierclub' ),
			'aa_invalid_reseller' => __( 'Choose a reseller from the list.', 'axellcore-atelierclub' ),
			'aa_email_exists'     => __( 'This e-mail is already registered.', 'axellcore-atelierclub' ),
			'aa_document_exists'  => __( 'This CPF/CNPJ is already registered.', 'axellcore-atelierclub' ),
			'aa_rate_limited'     => __( 'Too many attempts. Try again in a few minutes.', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * The partner stores filled in, in the order sent, without the empty
	 * positions: each with its revenda ID (0 for a custom store) and its text.
	 *
	 * @param array $params Submitted fields.
	 * @return array<int,array{id:int,title:string}>
	 */
	public static function submitted_resellers( array $params ) {
		$stores = array();
		foreach ( self::RESELLER_FIELDS as $field ) {
			$id    = absint( $params[ $field ] ?? 0 );
			$title = sanitize_text_field( $params[ $field . '_title' ] ?? '' );
			if ( 0 === $id && '' === $title ) {
				continue;
			}
			$stores[] = array(
				'id'    => $id,
				'title' => $title,
			);
		}
		return $stores;
	}
}
