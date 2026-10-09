<?php
/**
 * Render-time wiring of the application form (axell/form).
 *
 * The saved markup has the Interactivity directives (src/form/form/save.tsx).
 * What depends on the site is added here, per request: the admin-post URL for
 * the no-JavaScript submission, and the REST URL the store posts to.
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the site-specific attributes to the application form on render.
 */
final class Form_Block {

	/**
	 * Interactivity store namespace (src/form/form/view.ts).
	 */
	const STORE = 'axell/form';

	/**
	 * Singleton instance.
	 *
	 * @var Form_Block|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Form_Block
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
		add_filter( 'render_block_axell/form', array( $this, 'decorate' ), 10, 2 );
		add_filter( 'render_block_axell/form-atelier', array( $this, 'decorate' ), 10, 2 );
	}

	/**
	 * Point the form at admin-post.php and expose the REST URL to the store.
	 *
	 * @param string $content Rendered block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function decorate( $content, $block ) {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );
		if ( ! $processor->next_tag( array( 'tag_name' => 'FORM' ) ) ) {
			return $content;
		}

		// Every form submits through the same service (Form_Submission): with
		// JavaScript the store posts to /submit, without it the form posts to
		// admin-post.php. What happens (store, email) comes from the saved block.
		$processor->set_attribute( 'method', 'post' );
		$processor->set_attribute( 'action', admin_url( 'admin-post.php' ) );
		$processor->set_attribute( 'data-wp-interactive', self::STORE );
		$processor->set_attribute(
			'data-wp-context',
			(string) wp_json_encode(
				array(
					'status'      => self::result_from_query(),
					'errorDetail' => self::error_from_query()[0],
				)
			)
		);
		if ( null === $processor->get_attribute( 'data-wp-on--submit' ) ) {
			$processor->set_attribute( 'data-wp-on--submit', 'actions.submit' );
			$processor->set_attribute( 'novalidate', true );
		}
		// An address typed without https:// gets it on leaving the field.
		if ( null === $processor->get_attribute( 'data-wp-on--focusout' ) ) {
			$processor->set_attribute( 'data-wp-on--focusout', 'actions.completeUrl' );
		}

		wp_interactivity_state(
			'axell/autocomplete',
			array(
				'optionsUrl' => rest_url( Rest::NAMESPACE . '/options' ),
			)
		);

		// CPF/CNPJ field: uniqueness check and its messages, translated here.
		wp_interactivity_state(
			'axell/document',
			array(
				'documentUrl'    => rest_url( Rest::NAMESPACE . '/document' ),
				'invalidCpf'     => Document::invalid_error( 'cpf' )[1],
				'invalidCnpj'    => Document::invalid_error( 'cnpj' )[1],
				'registeredCpf'  => Document::registered_error( 'cpf' )[1],
				'registeredCnpj' => Document::registered_error( 'cnpj' )[1],
			)
		);

		// Cities of a UF, for the address block (country Brazil): same REST namespace.
		wp_interactivity_state(
			'axell/address',
			array(
				'citiesUrl'     => rest_url( Rest::NAMESPACE . '/cities' ),
				/**
				 * Countries whose cities come from the cities endpoint, so the city
				 * control shows a list or a search for them.
				 *
				 * @param string[] $countries Country codes. Default: Brazil.
				 */
				'cityCountries' => (array) apply_filters( 'axellcore_atelier_city_countries', array( 'BR' ) ),
			)
		);

		wp_interactivity_state(
			self::STORE,
			array(
				'restUrl'      => rest_url( Rest::NAMESPACE . '/submit' ),
				'isSubmitting' => static function () {
					return 'submitting' === self::status();
				},
				'isSuccess'    => static function () {
					return 'success' === self::status();
				},
				'isError'      => static function () {
					return 'error' === self::status();
				},
			)
		);

		return self::with_error( self::with_hidden_fields( $processor->get_updated_html(), $block ) );
	}

	/**
	 * After a no-JavaScript submission with an error a visitor can fix: its
	 * message in the error notice, and its field marked aria-invalid. With
	 * JavaScript the store fills the same message (context.errorDetail).
	 *
	 * @param string $html Form HTML.
	 * @return string
	 */
	private static function with_error( $html ) {
		list( $detail, $field ) = self::error_from_query();

		if ( '' !== $field ) {
			$p = new \WP_HTML_Tag_Processor( $html );
			while ( $p->next_tag() ) {
				if ( in_array( $p->get_tag(), array( 'INPUT', 'SELECT', 'TEXTAREA' ), true ) && $field === $p->get_attribute( 'name' ) && 'hidden' !== $p->get_attribute( 'type' ) ) {
					$p->set_attribute( 'aria-invalid', 'true' );
				}
			}
			$html = $p->get_updated_html();
		}

		$paragraph = sprintf(
			'<p class="axell-form-error-detail" data-wp-text="context.errorDetail" data-wp-bind--hidden="!context.errorDetail"%1$s>%2$s</p>',
			'' === $detail ? ' hidden' : '',
			esc_html( $detail )
		);
		$paragraph = str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $paragraph );

		// Inside the notice's own box (its first block, e.g. a styled group),
		// so the message takes the box's look; else first in the notice.
		$count  = 0;
		$inside = preg_replace( '/(<[^>]*\bdata-axell-notice-type="error"[^>]*>\s*<div\b[^>]*>)/', '$1' . $paragraph, $html, 1, $count );
		if ( $count ) {
			return (string) $inside;
		}
		return (string) preg_replace( '/(<[^>]*\bdata-axell-notice-type="error"[^>]*>)/', '$1' . $paragraph, $html, 1 );
	}

	/**
	 * Message and field of the error in the query (Form_Submission), or ''.
	 *
	 * @return array{0:string,1:string}
	 */
	private static function error_from_query() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only picks which message to show.
		$code  = isset( $_GET['axell-form-code'] ) ? sanitize_key( wp_unslash( $_GET['axell-form-code'] ) ) : '';
		$field = isset( $_GET['axell-form-field'] ) ? sanitize_key( wp_unslash( $_GET['axell-form-field'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$messages = Members::visitor_messages();
		if ( 'error' !== self::result_from_query() || ! isset( $messages[ $code ] ) ) {
			return array( '', '' );
		}
		return array( $messages[ $code ], $field );
	}

	/**
	 * Add the fields the submission service needs right after the <form> tag:
	 * the admin-post action, the post that has the form, the form id, and the
	 * honeypot (forms saved before these existed do not carry them).
	 *
	 * @param string $html  Form HTML.
	 * @param array  $block Parsed block.
	 * @return string
	 */
	private static function with_hidden_fields( $html, $block ) {
		$open = strpos( $html, '<form' );
		$end  = false === $open ? false : strpos( $html, '>', $open );
		if ( false === $end ) {
			return $html;
		}

		$fields = sprintf(
			'<input type="hidden" name="action" value="%1$s"/><input type="hidden" name="post_id" value="%2$d"/><input type="hidden" name="form_id" value="%3$s"/>',
			esc_attr( Form_Submission::ADMIN_ACTION ),
			(int) get_the_ID(),
			esc_attr( (string) ( $block['attrs']['formId'] ?? '' ) )
		);
		if ( false === strpos( $html, 'name="' . Members::HONEYPOT_FIELD . '"' ) ) {
			$fields .= '<div hidden><input type="text" name="' . esc_attr( Members::HONEYPOT_FIELD ) . '" tabindex="-1" autocomplete="off" aria-label="' . esc_attr__( 'Leave this field empty', 'axellcore-atelier' ) . '"/></div>';
		}

		// Drop a saved action field (older forms had the members action).
		$html = preg_replace( '/<input type="hidden" name="action" value="[^"]*"\s*\/?>/', '', $html, 1 );
		$end  = strpos( $html, '>', strpos( $html, '<form' ) );

		return substr( $html, 0, $end + 1 ) . $fields . substr( $html, $end + 1 );
	}

	/**
	 * Status of the form in the current render: from the query after a
	 * no-JavaScript submission, otherwise idle.
	 *
	 * @return string idle|success|error
	 */
	private static function result_from_query() {
		$result = isset( $_GET['axell-form'] ) ? sanitize_key( wp_unslash( $_GET['axell-form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which message to show.

		return in_array( $result, array( 'success', 'error' ), true ) ? $result : 'idle';
	}

	/**
	 * Status of the form whose directives are being processed, read from its
	 * context (the server-side version of the store's getters).
	 *
	 * @return string
	 */
	private static function status() {
		$context = wp_interactivity_get_context( self::STORE );

		return isset( $context['status'] ) ? $context['status'] : 'idle';
	}
}
