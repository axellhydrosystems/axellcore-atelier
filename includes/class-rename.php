<?php
/**
 * One-time move of the data saved under the plugin's former name
 * (axellcore-atelierclub): its options, post meta keys and the page
 * template `atelier-club`, now `atelier`.
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renames the former plugin's options and meta in place.
 */
final class Rename {

	/**
	 * Prefix of the former options and (with a leading underscore) meta keys.
	 */
	const OLD_PREFIX = 'axellcore_atelierclub_';

	/**
	 * Prefix they move to.
	 */
	const NEW_PREFIX = 'axellcore_atelier_';

	/**
	 * Former page template slug.
	 */
	const OLD_TEMPLATE = 'atelier-club';

	/**
	 * Options, without the prefix.
	 */
	const OPTIONS = array( 'settings', 'version', 'pages_hash', 'parts_hash', 'resellers_import' );

	/**
	 * Post meta keys, without the `_` and the prefix.
	 */
	const META = array( 'media', 'navigation' );

	/**
	 * Move everything once: only while the former version option exists
	 * (it is the last one renamed).
	 */
	public static function migrate() {
		if ( false === get_option( self::OLD_PREFIX . 'version' ) ) {
			return;
		}

		global $wpdb;

		foreach ( self::OPTIONS as $name ) {
			$old = self::OLD_PREFIX . $name;
			$new = self::NEW_PREFIX . $name;
			if ( false === get_option( $new ) ) {
				// Renamed in place: the value and its autoload stay as they were.
				$wpdb->update( $wpdb->options, array( 'option_name' => $new ), array( 'option_name' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				delete_option( $old );
			}
		}

		foreach ( self::META as $name ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_' . self::NEW_PREFIX . $name ), array( 'meta_key' => '_' . self::OLD_PREFIX . $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->postmeta,
			array( 'meta_value' => Plugin::TEMPLATE_SLUG ), // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'meta_key'   => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => self::OLD_TEMPLATE, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		// Options and meta changed under the caches.
		wp_cache_flush();
	}
}
