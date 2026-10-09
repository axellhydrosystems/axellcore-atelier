<?php
/**
 * Page cache purge when the plugin changes what pages show: activation,
 * deactivation, an update, a new Atelier page. Page caches (WP Super Cache
 * on production, among others) would otherwise keep serving the HTML from
 * before.
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Purges the page caches of the cache plugins that are active.
 */
final class Cache {

	/**
	 * Option holding the plugin version the caches were last purged for.
	 */
	const VERSION_OPTION = 'axellcore_atelier_version';

	/**
	 * Cache plugins: a function each exposes to purge everything, called
	 * when it exists (the plugin is active).
	 */
	const FUNCTIONS = array(
		'wp_cache_clear_cache',      // WP Super Cache.
		'rocket_clean_domain',       // WP Rocket.
		'w3tc_flush_all',            // W3 Total Cache.
		'sg_cachepress_purge_cache', // SiteGround Optimizer.
	);

	/**
	 * Cache plugins that purge everything on an action.
	 */
	const ACTIONS = array(
		'litespeed_purge_all',                // LiteSpeed Cache.
		'wpfc_clear_all_cache',               // WP Fastest Cache.
		'cache_enabler_clear_complete_cache', // Cache Enabler.
		'wphb_clear_page_cache',              // Hummingbird.
		'breeze_clear_all_cache',             // Breeze.
	);

	/**
	 * Register hooks.
	 */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'purge_after_update' ) );
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'purge_on_new_page' ), 10, 2 );
	}

	/**
	 * Purge every active page cache (and the Sucuri firewall's, when its
	 * API key is set). A failing cache plugin never breaks the request.
	 */
	public static function purge() {
		/**
		 * Functions that purge a whole page cache, called when they exist.
		 *
		 * @param string[] $functions Default: WP Super Cache, WP Rocket, W3 Total Cache, SiteGround Optimizer.
		 */
		foreach ( (array) apply_filters( 'axellcore_atelier_cache_functions', self::FUNCTIONS ) as $function ) {
			if ( is_string( $function ) && function_exists( $function ) ) {
				self::attempt( $function );
			}
		}
		foreach ( self::ACTIONS as $action ) {
			if ( has_action( $action ) ) {
				self::attempt(
					static function () use ( $action ) {
						do_action( $action ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- other plugins' actions.
					}
				);
			}
		}
		$firewall = array( 'SucuriScanFirewall', 'getKey' );
		if ( is_callable( $firewall ) && call_user_func( $firewall ) ) {
			self::attempt( array( 'SucuriScanFirewall', 'clearCache' ) );
		}

		/**
		 * Purge other caches when the plugin changes what pages show.
		 */
		do_action( 'axellcore_atelier_purge_cache' );
	}

	/**
	 * After the plugin was updated (any way: Plugins screen, updater, files),
	 * the first request purges once.
	 */
	public static function purge_after_update() {
		if ( get_option( self::VERSION_OPTION ) === AXELLCORE_ATELIER_VERSION ) {
			return;
		}
		update_option( self::VERSION_OPTION, AXELLCORE_ATELIER_VERSION );
		self::purge();
	}

	/**
	 * A different Atelier page changes which page gets the Atelier styles.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $value     New settings.
	 */
	public static function purge_on_new_page( $old_value, $value ) {
		$old = is_array( $old_value ) ? absint( $old_value['page_id'] ?? 0 ) : 0;
		$new = is_array( $value ) ? absint( $value['page_id'] ?? 0 ) : 0;
		if ( $old !== $new ) {
			self::purge();
		}
	}

	/**
	 * Call one purge, ignoring its errors.
	 *
	 * @param callable $callback Purge.
	 */
	private static function attempt( $callback ) {
		try {
			ob_start();
			call_user_func( $callback );
		} catch ( \Throwable $e ) {
			error_log( 'axellcore-atelier: cache purge failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- no UI here.
		} finally {
			ob_end_clean();
		}
	}
}
