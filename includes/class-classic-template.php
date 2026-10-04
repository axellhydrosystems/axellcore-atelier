<?php
/**
 * Classic-theme rendering of the Atelier landing page: a plugin page template
 * plus header and footer partials. Theme files named header-atelier.php and
 * footer-atelier.php take precedence over the plugin's (WooCommerce-style).
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic template selection and partial lookup.
 */
final class Classic_Template {

	/**
	 * Page slug the classic template applies to.
	 */
	const PAGE_SLUG = 'atelier';

	/**
	 * Singleton instance.
	 *
	 * @var Classic_Template|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Classic_Template
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
		add_filter( 'template_include', array( $this, 'include_template' ), 99 );
	}

	/**
	 * Whether the current request renders the Atelier page with a classic theme.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return is_page( self::PAGE_SLUG ) && ! wp_is_block_theme();
	}

	/**
	 * Use the plugin's page template for the Atelier page on classic themes.
	 *
	 * @param string $template Template chosen by WordPress.
	 * @return string
	 */
	public function include_template( $template ) {
		if ( ! self::is_active() ) {
			return $template;
		}
		return AXELLCORE_ATELIERCLUB_PATH . 'templates/atelier/page.php';
	}

	/**
	 * Path of a partial: the theme's copy if present, else the plugin's.
	 *
	 * @param string $file File name, e.g. header-atelier.php.
	 * @return string
	 */
	public static function locate( $file ) {
		$theme_file = locate_template( $file );
		if ( $theme_file ) {
			return $theme_file;
		}
		return (string) apply_filters( 'axellcore_atelier_template_path', AXELLCORE_ATELIERCLUB_PATH . 'templates/atelier/' . $file, $file );
	}
}
