<?php
/**
 * Keeps Weglot off the Atelier pages.
 *
 * The landing is Portuguese only and has its own header: Weglot's language
 * switcher, stylesheets, hreflang links and output buffer don't belong there.
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cancels Weglot on requests for the Atelier page (and the pages under it)
 * and excludes them from translation. The path comes from the page chosen in
 * Atelier > Settings, so its slug can change.
 */
final class Weglot {

	/**
	 * Singleton instance.
	 *
	 * @var Weglot|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Weglot
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
	 * Register hooks. Runs when the plugin file loads: Weglot reads
	 * `weglot_cancel_init` on `plugins_loaded`.
	 */
	public function register_hooks() {
		add_filter( 'weglot_cancel_init', array( $this, 'cancel_init' ) );
		add_filter( 'weglot_exclude_urls', array( $this, 'exclude_urls' ) );
	}

	/**
	 * Don't start Weglot at all on a request for an Atelier page.
	 *
	 * @param bool $cancel Whether another filter already cancelled it.
	 * @return bool
	 */
	public function cancel_init( $cancel ) {
		if ( $cancel || is_admin() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $cancel;
		}
		$path = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$home = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		return self::is_atelier_path( $path, self::atelier_path() );
	}

	/**
	 * Path of the Atelier page below the site's home (e.g. /atelier), or ''
	 * without one. get_page_uri() needs no rewrite rules, so this works on
	 * plugins_loaded, when Weglot asks.
	 *
	 * @return string
	 */
	public static function atelier_path() {
		$page = Settings::page();
		return $page ? '/' . trim( (string) get_page_uri( $page ), '/' ) : '';
	}

	/**
	 * Exclude the Atelier pages in every language, without the switcher, so
	 * links to them on translated pages keep the Portuguese URL.
	 *
	 * @param array $exclude_urls Weglot's exclusions: [regex, languages, behavior, button displayed].
	 * @return array
	 */
	public function exclude_urls( $exclude_urls ) {
		$exclude_urls = is_array( $exclude_urls ) ? $exclude_urls : array();
		$base         = self::atelier_path();
		if ( '' !== $base ) {
			// Weglot wraps the pattern in # delimiters.
			$exclude_urls[] = array( '^' . preg_quote( $base, '#' ) . '(/|$)', null, 'NOT_TRANSLATED', false );
		}
		return $exclude_urls;
	}

	/**
	 * Whether a path (relative to the home URL) is the Atelier page's or below it.
	 *
	 * @param string $path Request path.
	 * @param string $base Path of the Atelier page (atelier_path()).
	 * @return bool
	 */
	public static function is_atelier_path( $path, $base ) {
		return '' !== $base && ( untrailingslashit( $base ) === untrailingslashit( $path ) || 0 === strpos( $path, untrailingslashit( $base ) . '/' ) );
	}
}
