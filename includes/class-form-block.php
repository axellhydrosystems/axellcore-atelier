<?php
/**
 * Render-time wiring of the application form (axell/form).
 *
 * The saved markup has the Interactivity directives (src/form/form/save.tsx).
 * What depends on the site is added here, per request: the admin-post URL for
 * the no-JavaScript submission, and the REST URL the store posts to.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

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
	}

	/**
	 * Point the form at admin-post.php and expose the REST URL to the store.
	 *
	 * @param string $content Rendered block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function decorate( $content, $block ) {
		if ( empty( $block['attrs']['submitsToRest'] ) || '' === trim( $content ) ) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );
		if ( ! $processor->next_tag( array( 'tag_name' => 'FORM' ) ) ) {
			return $content;
		}

		$processor->set_attribute( 'method', 'post' );
		$processor->set_attribute( 'action', admin_url( 'admin-post.php' ) );

		// Result of a no-JavaScript submission (Members::handle_form_post).
		$status = self::result_from_query();
		$processor->set_attribute( 'data-wp-context', (string) wp_json_encode( array( 'status' => $status ) ) );

		wp_interactivity_state(
			'axell/autocomplete',
			array(
				'optionsUrl' => rest_url( Rest::NAMESPACE . '/options' ),
			)
		);

		wp_interactivity_state(
			self::STORE,
			array(
				'restUrl'      => rest_url( Rest::NAMESPACE . '/members' ),
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

		return $processor->get_updated_html();
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
