<?php
/**
 * Frontend JS (input masks, cities cascade) on the Atelier landing template
 * and on pages with an application form. The landing renders like the
 * section pages: the theme's global styles, block styles and presets (the
 * legacy isolated stylesheets are gone).
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend script enqueue.
 */
final class Assets {

	/**
	 * Singleton instance.
	 *
	 * @var Assets|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Assets
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Whether the current request is rendering our (production) landing
	 * page template.
	 *
	 * @return bool
	 */
	private function is_our_template() {
		return is_page_template( Plugin::TEMPLATE_SLUG ) || Classic_Template::is_active();
	}

	/**
	 * Enqueue the frontend script on the atelier template and on pages with
	 * an application form.
	 */
	public function enqueue_frontend_assets() {
		if ( ! $this->is_our_template() ) {
			// Theme-styled pages with an application form (the adesão child
			// page) keep their own look, but still need the input masks and
			// the cities cascade from frontend.js.
			if ( is_singular() && ( has_block( 'axell/form', get_post() ) || has_block( 'axell/form-atelier', get_post() ) ) ) {
				$this->enqueue_frontend_script();
			}
			return;
		}

		$this->enqueue_frontend_script();
	}

	/**
	 * The frontend.js script (input masks, cities cascade) and its REST root. The REST
	 * endpoints are public and unauthenticated, so no nonce is localized here.
	 */
	private function enqueue_frontend_script() {
		wp_enqueue_script(
			'aa-frontend',
			AXELLCORE_ATELIER_URL . 'assets/js/frontend.js',
			array(),
			AXELLCORE_ATELIER_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		wp_localize_script(
			'aa-frontend',
			'aaRest',
			array( 'root' => esc_url_raw( trailingslashit( rest_url( Rest::NAMESPACE ) ) ) )
		);
	}

	/**
	 * Register the DataViews/DataForm stylesheet the admin apps share
	 * (build/admin/dataviews/) and return its handle, to list as a dependency.
	 *
	 * @return string
	 */
	public static function admin_dataviews_style() {
		$handle = 'axellcore-atelier-admin-dataviews';
		$asset  = AXELLCORE_ATELIER_PATH . 'build/admin/dataviews/index.asset.php';
		if ( ! wp_style_is( $handle, 'registered' ) && file_exists( $asset ) ) {
			$version = ( require $asset )['version'];
			wp_register_style( $handle, AXELLCORE_ATELIER_URL . 'build/admin/dataviews/style-index.css', array( 'wp-components' ), $version );
		}
		return $handle;
	}
}
