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
 * Creates aac_member posts from a form submission.
 */
final class Members {

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
		'nome',
		'escritorio',
		'email',
		'telefone',
		'atuacao',
		'tipoDoc',
		'documento',
		'rua',
		'numero',
		'bairro',
		'cidade',
		'uf',
		'cep',
		'regulamento',
	);

	/**
	 * Optional text-meta fields, stored verbatim (sanitize_text_field) under
	 * `_aac_{field}`. `email` and `portfolio` are handled separately (their
	 * own sanitizers); `uf`/`cidade` are handled by the location-resolution
	 * step, not stored as plain meta.
	 *
	 * @var string[]
	 */
	const TEXT_META_FIELDS = array(
		'escritorio',
		'telefone',
		'registro',
		'atuacao',
		'tipoDoc',
		'documento',
		'rua',
		'numero',
		'complemento',
		'bairro',
		'referencia',
		'cep',
	);

	/**
	 * Partner store slots. Each slot stores its ID (`_aac_lojaN`, empty for free
	 * text) and the text shown to the user (`_aac_lojaN_titulo`).
	 *
	 * @var string[]
	 */
	const LOJA_FIELDS = array( 'loja1', 'loja2', 'loja3', 'loja4', 'loja5' );

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
				if ( Member::POST_TYPE !== $post_type ) {
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
				return new \WP_Error(
					'aac_missing_field',
					/* translators: %s: form field name. */
					sprintf( __( 'Missing required field: %s', 'axellcore-atelierclub' ), $field ),
					array( 'status' => 400 )
				);
			}
		}

		$email = sanitize_email( $params['email'] );
		if ( '' === $email || ! is_email( $email ) ) {
			return new \WP_Error( 'aac_invalid_email', __( 'Invalid email address.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
		}

		$uf        = strtoupper( sanitize_text_field( $params['uf'] ) );
		$city_code = absint( $params['cidade'] );
		$city_term = Locations::instance()->resolve_city_term( $uf, $city_code );
		if ( null === $city_term ) {
			return new \WP_Error( 'aac_invalid_location', __( 'Invalid state/city.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => Member::POST_TYPE,
				'post_title'  => sanitize_text_field( $params['nome'] ),
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error( 'aac_insert_failed', __( 'Could not save your application.', 'axellcore-atelierclub' ), array( 'status' => 500 ) );
		}

		wp_set_object_terms( $post_id, array( $city_term ), Locations::TAXONOMY );

		update_post_meta( $post_id, '_aac_email', $email );
		if ( ! empty( $params['portfolio'] ) ) {
			update_post_meta( $post_id, '_aac_portfolio', esc_url_raw( $params['portfolio'] ) );
		}
		update_post_meta( $post_id, '_aac_uf', $uf );

		foreach ( self::TEXT_META_FIELDS as $field ) {
			if ( ! empty( $params[ $field ] ) ) {
				update_post_meta( $post_id, '_aac_' . $field, sanitize_text_field( $params[ $field ] ) );
			}
		}

		foreach ( self::LOJA_FIELDS as $field ) {
			$id    = absint( $params[ $field ] ?? 0 );
			$title = sanitize_text_field( $params[ $field . '_titulo' ] ?? '' );
			if ( 0 === $id && '' === $title ) {
				continue;
			}
			// Custom store: a text "Nome - UF Cidade" that matches becomes a
			// pending revenda (see axellcore-revendas), and its ID is kept.
			if ( 0 === $id ) {
				$id = (int) apply_filters( 'axellcore_atelierclub_loja_text', 0, $title );
			}
			update_post_meta( $post_id, '_aac_' . $field, $id > 0 ? $id : '' );
			update_post_meta( $post_id, '_aac_' . $field . '_titulo', $title );
		}

		return array(
			'success' => true,
			'id'      => $post_id,
		);
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
				'aac_rate_limited',
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
}
