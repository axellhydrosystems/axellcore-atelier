<?php
/**
 * Google reCAPTCHA on the Atelier forms (Atelier → Settings → Integrations),
 * in the mold of Elementor Pro's form reCAPTCHA: v3 (invisible, a score) or
 * v2 (the "I'm not a robot" box), each with its own keys. Off on a local
 * site (is_local()) when the setting says so.
 *
 * The form blocks get the script and, for v2, the box (render filter); the
 * submission service checks the token with Google before anything is stored.
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The reCAPTCHA of the form blocks.
 */
final class Recaptcha {

	/**
	 * A local address: http(s)://localhost, 127.0.0.1 or a .local or .test
	 * host, with or without a port.
	 */
	const LOCAL_PATTERN = '#^https?://(localhost|127\.0\.0\.1|[a-z0-9.-]+\.(local|test))(:\d+)?(/|$)#i';

	/**
	 * Google's token check.
	 */
	const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Google's script.
	 */
	const SCRIPT_URL = 'https://www.google.com/recaptcha/api.js';

	/**
	 * The v3 action of a form submission.
	 */
	const ACTION = 'axell_form';

	/**
	 * Script handle.
	 */
	const SCRIPT = 'axellcore-atelier-recaptcha';

	/**
	 * The field Google's script fills with the token.
	 */
	const FIELD = 'g-recaptcha-response';

	/**
	 * The v3 score threshold when none is set.
	 */
	const THRESHOLD = 0.5;

	/**
	 * Singleton instance.
	 *
	 * @var Recaptcha|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Recaptcha
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
	 * Hook into the form blocks' render, after Form_Block::decorate().
	 */
	public function register_hooks() {
		add_filter( 'render_block_axell/form', array( $this, 'decorate' ), 20 );
		add_filter( 'render_block_axell/form-atelier', array( $this, 'decorate' ), 20 );
	}

	/**
	 * Whether an address is local (LOCAL_PATTERN). The site is local too
	 * when its environment type is "local", whatever its address.
	 *
	 * @param string|null $url Address; the site's by default.
	 * @return bool
	 */
	public static function is_local( $url = null ) {
		if ( null === $url && function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return true;
		}
		return 1 === preg_match( self::LOCAL_PATTERN, null === $url ? (string) home_url( '/' ) : (string) $url );
	}

	/**
	 * The version chosen: v3 (default) or v2.
	 *
	 * @return string
	 */
	public static function version() {
		return 'v2' === Settings::get( 'recaptcha_version' ) ? 'v2' : 'v3';
	}

	/**
	 * The site key of the version chosen.
	 *
	 * @return string
	 */
	public static function site_key() {
		return trim( (string) Settings::get( 'v2' === self::version() ? 'recaptcha_site_key' : 'recaptcha_v3_site_key' ) );
	}

	/**
	 * The secret key of the version chosen.
	 *
	 * @return string
	 */
	public static function secret_key() {
		return trim( (string) Settings::get( 'v2' === self::version() ? 'recaptcha_secret_key' : 'recaptcha_v3_secret_key' ) );
	}

	/**
	 * The v3 score a submission needs, 0 to 1 (0.5 when none is set).
	 *
	 * @return float
	 */
	public static function threshold() {
		$value = Settings::get( 'recaptcha_v3_threshold' );
		return is_numeric( $value ) ? max( 0.0, min( 1.0, (float) $value ) ) : self::THRESHOLD;
	}

	/**
	 * Whether the forms are protected: on, with both keys of the version,
	 * and not on a local address when the setting skips those.
	 *
	 * @return bool
	 */
	public static function active() {
		if ( ! Settings::get( 'recaptcha_enabled' ) || '' === self::site_key() || '' === self::secret_key() ) {
			return false;
		}
		return ! ( Settings::get( 'recaptcha_skip_local' ) && self::is_local() );
	}

	/**
	 * The form block with Google's script; v2 also with the box, before the
	 * submit button, and v3 with its key for the store (src/form/form/view.ts).
	 *
	 * @param string $content Rendered block HTML.
	 * @return string
	 */
	public function decorate( $content ) {
		if ( ! self::active() || false === strpos( $content, '<form' ) ) {
			return $content;
		}

		$v2   = 'v2' === self::version();
		$args = $v2 ? array( 'hl' => str_replace( '_', '-', determine_locale() ) ) : array( 'render' => self::site_key() );
		wp_enqueue_script( self::SCRIPT, add_query_arg( array_map( 'rawurlencode', $args ), self::SCRIPT_URL ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google's script, unversioned.

		if ( ! $v2 ) {
			wp_interactivity_state(
				Form_Block::STORE,
				array(
					'recaptcha' => array(
						'siteKey' => self::site_key(),
						'action'  => self::ACTION,
					),
				)
			);
			return $content;
		}

		// The box on its own line before the submit button's row: before its
		// Buttons block or, when a group wraps that block (the button beside
		// a text), before the group.
		$box = sprintf( '<div class="g-recaptcha axell-form-recaptcha" data-sitekey="%s"></div>', esc_attr( self::site_key() ) );
		$at  = strpos( $content, 'type="submit"' );
		$at  = false === $at ? false : strrpos( substr( $content, 0, $at ), '<div class="wp-block-buttons' );
		if ( false !== $at && preg_match( '/<div class="wp-block-group[^>]*>\s*$/', substr( $content, 0, $at ), $group ) ) {
			$at -= strlen( $group[0] );
		}
		if ( false === $at ) {
			$at = strrpos( $content, '</form>' );
		}
		return false === $at ? $content : substr( $content, 0, $at ) . $box . substr( $content, $at );
	}

	/**
	 * Check a submission's token with Google.
	 *
	 * @param array  $params Submitted fields.
	 * @param string $ip     Client address.
	 * @return true|\WP_Error
	 */
	public static function verify( array $params, $ip ) {
		$token = isset( $params[ self::FIELD ] ) && is_string( $params[ self::FIELD ] ) ? trim( $params[ self::FIELD ] ) : '';
		if ( '' === $token ) {
			return self::error();
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => self::secret_key(),
					'response' => $token,
					'remoteip' => (string) $ip,
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return self::error();
		}

		$result = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $result ) || empty( $result['success'] ) ) {
			return self::error();
		}
		if ( 'v3' === self::version() ) {
			$action = $result['action'] ?? '';
			$score  = isset( $result['score'] ) ? (float) $result['score'] : -1.0;
			if ( self::ACTION !== $action || $score < self::threshold() ) {
				return self::error();
			}
		}
		return true;
	}

	/**
	 * The error of a submission reCAPTCHA did not let through.
	 *
	 * @return \WP_Error
	 */
	private static function error() {
		return new \WP_Error( 'aa_recaptcha_failed', Members::visitor_messages()['aa_recaptcha_failed'], array( 'status' => 400 ) );
	}
}
