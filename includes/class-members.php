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
	 * Member fields kept as user meta, named as the adesão form (and the REST
	 * API) names them; Members::put() stores each under its meta key (META).
	 * `email` and `url` are user fields; `state`/`city` go through the
	 * location step; `profile_type` is never stored, it follows the CPF/CNPJ.
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
	 * The form's partner store fields: each sends a revenda ID (`resellerN`)
	 * and its text (`resellerN_title`, a "Nome - UF Cidade" for a store not
	 * in the list). Stored together as RESELLER_META.
	 *
	 * @var string[]
	 */
	const RESELLER_FIELDS = array( 'reseller1', 'reseller2', 'reseller3', 'reseller4', 'reseller5' );

	/**
	 * Meta key of the partner stores: their revenda IDs, in order, separated
	 * by commas ("2625,2802"). Their text always comes from the revenda.
	 */
	const RESELLER_META = 'reseller_ids';

	/**
	 * Meta keys of the member fields that WooCommerce also has, as its
	 * customers' billing data (WC_Customer_Data_Store), and the number and
	 * neighbourhood as the Brazilian Market plugin keeps them. The fields not
	 * listed keep their own name as meta key.
	 *
	 * @var array<string,string>
	 */
	const META = array(
		'company'        => 'billing_company',
		'phone'          => 'billing_phone',
		'country'        => 'billing_country',
		'address_street' => 'billing_address_1',
		'address_number' => 'billing_number',
		'address_2'      => 'billing_address_2',
		'neighborhood'   => 'billing_neighborhood',
		'state'          => 'billing_state',
		'city'           => 'billing_city',
		'postal'         => 'billing_postcode',
	);

	/**
	 * The CPF/CNPJ field is stored, as the Brazilian Market plugin does, under
	 * one of two keys by its type; the other is removed.
	 *
	 * @var array<string,string>
	 */
	const DOCUMENT_META = array(
		'cpf'  => 'billing_cpf',
		'cnpj' => 'billing_cnpj',
	);

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

		// The portfolio: https:// added when missing, then a valid address.
		$url = Format::url( (string) ( $params['url'] ?? '' ) );
		if ( null === $url ) {
			return self::field_error( 'aa_invalid_url', self::invalid_url_message(), 'url' );
		}

		// Brazil only: the form's country is fixed.
		$country = strtoupper( trim( (string) ( $params['country'] ?? '' ) ) );
		if ( '' !== $country && 'BR' !== $country ) {
			return self::field_error( 'aa_invalid_country', __( 'Only Brazil is accepted.', 'axellcore-atelierclub' ), 'country' );
		}
		$country  = 'BR';
		$location = self::location( $country, (string) $params['state'], (string) $params['city'] );
		if ( is_wp_error( $location ) ) {
			return $location;
		}
		list( $uf, $city ) = $location;

		if ( 'BR' === $country ) {
			// The rules of the form's masks (form-address/view.ts): a mobile
			// has 9 after the area code, a landline starts with 2 to 5.
			if ( ! preg_match( '/^\d{2}(9\d{8}|[2-5]\d{7})$/', self::digits( (string) $params['phone'] ) ) ) {
				return self::field_error( 'aa_invalid_phone', __( 'Enter a phone number with area code.', 'axellcore-atelierclub' ), 'phone' );
			}
			if ( ! preg_match( '/^\d{8}$/', self::digits( (string) $params['postal'] ) ) ) {
				return self::field_error( 'aa_invalid_postal', __( 'Enter a CEP with 8 digits.', 'axellcore-atelierclub' ), 'postal' );
			}
		}

		$document      = Document::normalize( (string) $params['br_revenue_id'] );
		$document_type = Document::type_for( $document, Document::type_of( (string) $params['profile_type'] ) );
		if ( ! Document::is_valid( $document, $document_type ) ) {
			list( $code, $message ) = Document::invalid_error( $document_type );
			return self::field_error( $code, $message, 'br_revenue_id' );
		}

		foreach ( self::RESELLER_FIELDS as $field ) {
			$id    = absint( $params[ $field ] ?? 0 );
			$title = trim( sanitize_text_field( (string) ( $params[ $field . '_title' ] ?? '' ) ) );
			if ( $id && ( Resellers::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) ) {
				return self::field_error( 'aa_invalid_reseller', __( 'Choose a reseller from the list.', 'axellcore-atelierclub' ), $field . '_title' );
			}
			// A store given by text becomes a pending revenda: only
			// "Nome - UF Cidade" can (the form's custom store composes it).
			if ( ! $id && '' !== $title && null === Reseller_Store::parse_store_text( $title ) ) {
				return self::field_error( 'aa_invalid_store', __( 'Enter the store as "Name - UF City".', 'axellcore-atelierclub' ), $field . '_title' );
			}
		}

		if ( email_exists( $email ) ) {
			return self::field_error( 'aa_email_exists', __( 'This e-mail is already registered.', 'axellcore-atelierclub' ), 'email', 409 );
		}
		if ( self::document_exists( $document ) ) {
			list( $code, $message ) = Document::registered_error( $document_type );
			return self::field_error( $code, $message, 'br_revenue_id', 409 );
		}

		$fullname = sanitize_text_field( $params['fullname'] );
		$name     = self::name_meta( $fullname );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => self::username_for( $email ),
				'first_name'   => $name['first_name'],
				'last_name'    => $name['last_name'],
				'user_email'   => $email,
				// Never shown: the member sets a password when access is granted.
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => $fullname,
				'user_url'     => $url,
				'nickname'     => $fullname,
				'role'         => Settings::get( 'pending_on_create' ) ? Member::ROLE_PENDING : Member::ROLE,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error( 'aa_insert_failed', __( 'Could not save your application.', 'axellcore-atelierclub' ), array( 'status' => 500 ) );
		}

		$fields = array(
			'country'       => $country,
			'state'         => $uf,
			'city'          => $city,
			'br_revenue_id' => $document,
		);
		foreach ( self::TEXT_META_FIELDS as $field ) {
			if ( ! isset( $fields[ $field ] ) && ! empty( $params[ $field ] ) ) {
				$fields[ $field ] = sanitize_text_field( $params[ $field ] );
			}
		}
		foreach ( $fields as $field => $value ) {
			self::put( $user_id, $field, $value );
		}
		// WooCommerce's billing name and e-mail, as its checkout keeps them.
		$meta = array(
			'billing_first_name' => $name['billing_first_name'],
			'billing_last_name'  => $name['billing_last_name'],
			'billing_email'      => $email,
		);

		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}

		// The stores filled in, in order, by their revenda: a store given by
		// text ("Nome - UF Cidade") becomes a pending one (Reseller_Store).
		$ids = array();
		foreach ( self::submitted_resellers( $params ) as $store ) {
			$ids[] = $store['id'] ? $store['id'] : (int) apply_filters( 'axellcore_atelierclub_reseller_text', 0, $store['title'] );
		}
		self::set_reseller_ids( $user_id, $ids );

		/**
		 * A member was created from the form (the e-mails go out here).
		 *
		 * @param int $user_id New member.
		 */
		do_action( 'axellcore_atelierclub_member_created', (int) $user_id );

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
	 * The meta key of a member field.
	 *
	 * @param string $field Field name (form / REST).
	 * @return string
	 */
	public static function meta_key( $field ) {
		return self::META[ $field ] ?? $field;
	}

	/**
	 * A member field as stored ('' when unset). The CPF/CNPJ comes from its
	 * key by type, the profile type from the CPF/CNPJ.
	 *
	 * @param int    $user_id User ID.
	 * @param string $field   Field name.
	 * @return string
	 */
	public static function get( $user_id, $field ) {
		if ( 'br_revenue_id' === $field ) {
			return self::document_of( $user_id );
		}
		if ( 'profile_type' === $field ) {
			return self::profile_type_of( self::document_of( $user_id ) );
		}
		return (string) get_user_meta( $user_id, self::meta_key( $field ), true );
	}

	/**
	 * Store a checked member field in its stored format (the phone with
	 * +55, the CEP and the CPF/CNPJ without masks); an empty value deletes
	 * it. The profile type is never stored.
	 *
	 * @param int    $user_id User ID.
	 * @param string $field   Field name.
	 * @param string $value   Value.
	 */
	public static function put( $user_id, $field, $value ) {
		$value = trim( (string) $value );
		switch ( $field ) {
			case 'profile_type':
				return;
			case 'br_revenue_id':
				$document = Document::normalize( $value );
				$type     = '' !== $document ? Document::type_for( $document, '' ) : '';
				foreach ( self::DOCUMENT_META as $document_type => $key ) {
					if ( $document_type === $type ) {
						update_user_meta( $user_id, $key, $document );
					} else {
						delete_user_meta( $user_id, $key );
					}
				}
				return;
			case 'phone':
				$value = Format::phone_store( $value );
				break;
			case 'postal':
				$value = Format::digits( $value );
				break;
			case 'country':
				$value = strtoupper( $value );
				break;
			case 'primary_focus':
				// The form sends its option value: stored by its label.
				$value = Admin_Rest::PRIMARY_FOCUS_SLUGS[ $value ] ?? $value;
				break;
		}
		if ( '' === $value ) {
			delete_user_meta( $user_id, self::meta_key( $field ) );
		} else {
			update_user_meta( $user_id, self::meta_key( $field ), $value );
		}
	}

	/**
	 * The member's partner stores: revenda IDs, in order.
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public static function reseller_ids( $user_id ) {
		$ids = array_map( 'absint', explode( ',', (string) get_user_meta( $user_id, self::RESELLER_META, true ) ) );
		return array_values( array_filter( $ids ) );
	}

	/**
	 * Store the partner stores (once each, in order, five at most); none
	 * deletes the meta.
	 *
	 * @param int   $user_id User ID.
	 * @param int[] $ids     Revenda IDs.
	 */
	public static function set_reseller_ids( $user_id, array $ids ) {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, count( self::RESELLER_FIELDS ) );
		if ( $ids ) {
			update_user_meta( $user_id, self::RESELLER_META, implode( ',', $ids ) );
		} else {
			delete_user_meta( $user_id, self::RESELLER_META );
		}
	}

	/**
	 * A partner store's text, from its revenda (published or pending), as
	 * the form's search shows it: '' when the revenda no longer exists.
	 *
	 * @param int $id Revenda ID.
	 * @return string
	 */
	public static function reseller_title( $id ) {
		$post = $id ? get_post( (int) $id ) : null;
		if ( ! $post instanceof \WP_Post || Resellers::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return '';
		}
		return Label_Template::render( $post, Form_Directives::RESELLER_TEMPLATE );
	}

	/**
	 * The member's CPF/CNPJ (normalized), '' when none.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function document_of( $user_id ) {
		foreach ( self::DOCUMENT_META as $key ) {
			$document = (string) get_user_meta( $user_id, $key, true );
			if ( '' !== $document ) {
				return $document;
			}
		}
		return '';
	}

	/**
	 * The profile type a CPF/CNPJ means: individual for a valid CPF,
	 * legal_entity for a valid CNPJ, '' otherwise.
	 *
	 * @param string $document CPF/CNPJ, masked or not.
	 * @return string
	 */
	public static function profile_type_of( $document ) {
		$document = Document::normalize( $document );
		if ( '' === $document ) {
			return '';
		}
		$type = Document::type_for( $document, '' );
		if ( ! Document::is_valid( $document, $type ) ) {
			return '';
		}
		return 'cpf' === $type ? 'individual' : 'legal_entity';
	}

	/**
	 * A full name split as first name (the first word) and last name (the
	 * rest).
	 *
	 * @param string $fullname Full name.
	 * @return array{0:string,1:string}
	 */
	public static function split_name( $fullname ) {
		$parts = preg_split( '/\s+/', trim( (string) $fullname ), 2 );
		return array( (string) ( $parts[0] ?? '' ), (string) ( $parts[1] ?? '' ) );
	}

	/**
	 * The name meta of a full name: first and last name, and the same as
	 * WooCommerce's billing name.
	 *
	 * @param string $fullname Full name.
	 * @return array<string,string>
	 */
	public static function name_meta( $fullname ) {
		list( $first, $last ) = self::split_name( $fullname );
		return array(
			'first_name'         => $first,
			'last_name'          => $last,
			'billing_first_name' => $first,
			'billing_last_name'  => $last,
		);
	}

	/**
	 * Whether a member user already has this CPF/CNPJ.
	 *
	 * @param string $document Normalized CPF/CNPJ (Document::normalize()).
	 * @param int    $exclude  User ID to ignore (the one being edited).
	 * @return bool
	 */
	public static function document_exists( $document, $exclude = 0 ) {
		$keys = array();
		foreach ( self::DOCUMENT_META as $key ) {
			$keys[] = array(
				'key'   => $key,
				'value' => $document,
			);
		}
		$ids = get_users(
			array(
				'meta_query' => array_merge( array( 'relation' => 'OR' ), $keys ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- uniqueness check on two keys.
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
	 * A member's CPF/CNPJ as stored (normalized), or why it can't be: check
	 * digits for the profile type, and no other user with it. Empty is
	 * allowed (clears it). Shared by the Members screen and the user profile.
	 *
	 * @param int    $user_id      Member being edited.
	 * @param string $document     Typed value.
	 * @param string $profile_type Profile type (individual / legal_entity).
	 * @return string|\WP_Error
	 */
	public static function validate_document_for( $user_id, $document, $profile_type ) {
		$document = Document::normalize( $document );
		if ( '' === $document ) {
			return '';
		}
		$type = Document::type_for( $document, Document::type_of( $profile_type ) );
		if ( ! Document::is_valid( $document, $type ) ) {
			list( $code, $message ) = Document::invalid_error( $type );
			return new \WP_Error( $code, $message, array( 'status' => 400 ) );
		}
		if ( self::document_exists( $document, $user_id ) ) {
			list( $code, $message ) = Document::registered_error( $type );
			return new \WP_Error( $code, $message, array( 'status' => 409 ) );
		}
		return $document;
	}

	/**
	 * Why a site address was refused.
	 *
	 * @return string
	 */
	public static function invalid_url_message() {
		return __( 'Enter a valid address, such as https://yoursite.com.br.', 'axellcore-atelierclub' );
	}

	/**
	 * Only the digits of a value (phone, CEP).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function digits( $value ) {
		return Format::digits( $value );
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
			'aa_invalid_cpf'      => Document::invalid_error( 'cpf' )[1],
			'aa_invalid_cnpj'     => Document::invalid_error( 'cnpj' )[1],
			'aa_invalid_reseller' => __( 'Choose a reseller from the list.', 'axellcore-atelierclub' ),
			'aa_email_exists'     => __( 'This e-mail is already registered.', 'axellcore-atelierclub' ),
			'aa_cpf_exists'       => Document::registered_error( 'cpf' )[1],
			'aa_cnpj_exists'      => Document::registered_error( 'cnpj' )[1],
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
