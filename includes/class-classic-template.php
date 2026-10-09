<?php
/**
 * Classic-theme rendering of the Atelier landing page: a plugin page template
 * plus header and footer partials. Theme files named header-atelier.php and
 * footer-atelier.php take precedence over the plugin's (WooCommerce-style).
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic template selection and partial lookup.
 */
final class Classic_Template {


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
		add_filter( 'theme_page_templates', array( $this, 'page_templates' ) );
	}

	/**
	 * The Atelier template in a classic theme's list of page templates: the
	 * editor (and the REST API) keep only listed templates, so saving the page
	 * set it back to the default one.
	 *
	 * @param array<string,string> $templates Template slug => name.
	 * @return array<string,string>
	 */
	public function page_templates( $templates ) {
		if ( ! wp_is_block_theme() ) {
			$templates[ Plugin::TEMPLATE_SLUG ] = __( 'Atelier Axell', 'axellcore-atelier' );
		}
		return $templates;
	}

	/**
	 * Whether the current request renders the Atelier page with a classic theme.
	 *
	 * @return bool
	 */
	public static function is_active() {
		$page = Settings::page();
		return $page && ! wp_is_block_theme() && is_page( $page->ID );
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
		return AXELLCORE_ATELIER_PATH . 'templates/atelier/page.php';
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
		return (string) apply_filters( 'axellcore_atelier_template_path', AXELLCORE_ATELIER_PATH . 'templates/atelier/' . $file, $file );
	}

	/**
	 * The page as the block template renders it in a block theme
	 * (templates/atelier.html inside .wp-site-blocks), with the header
	 * and footer partials in place of its template parts. Called before
	 * wp_head(), as a block theme does, so the styles the blocks need are
	 * printed in the head.
	 *
	 * @return string
	 */
	public static function render_page() {
		$parts  = array(
			'axellcore-header' => 'header-atelier.php',
			'axellcore-footer' => 'footer-atelier.php',
		);
		$markup = (string) file_get_contents( AXELLCORE_ATELIER_PATH . 'templates/' . Plugin::TEMPLATE_SLUG . '.html' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$markup = (string) preg_replace_callback(
			'#<!-- wp:template-part (\{.*?\}) /-->#',
			static function ( $m ) use ( $parts ) {
				$attrs = json_decode( $m[1], true );
				$slug  = is_array( $attrs ) ? (string) ( $attrs['slug'] ?? '' ) : '';
				if ( ! isset( $parts[ $slug ] ) ) {
					return '';
				}
				ob_start();
				load_template( self::locate( $parts[ $slug ] ), false );
				return '<!-- wp:html --><div class="wp-block-template-part">' . ob_get_clean() . '</div><!-- /wp:html -->';
			},
			$markup
		);
		return '<div class="wp-site-blocks">' . do_blocks( $markup ) . '</div>';
	}
}
