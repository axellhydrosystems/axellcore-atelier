<?php
/**
 * The Atelier admin screens without other plugins' and the theme's notices
 * (promotions, rating requests, onboarding): WordPress's and this plugin's
 * notices stay.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notices on the Atelier admin screens.
 */
final class Admin_Notices {

	/**
	 * Admin page slugs of the Atelier menu.
	 */
	const PAGES = array( Member::ADMIN_PAGE, Members_Export::PAGE, Settings::PAGE, Resellers_Admin::ADMIN_PAGE );

	/**
	 * Notice hooks.
	 */
	const HOOKS = array( 'admin_notices', 'all_admin_notices', 'user_admin_notices', 'network_admin_notices' );

	/**
	 * Singleton instance.
	 *
	 * @var Admin_Notices|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Admin_Notices
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
		// Before the notice hooks run (they fire after in_admin_header).
		add_action( 'in_admin_header', array( $this, 'remove_foreign' ), PHP_INT_MAX );
	}

	/**
	 * On an Atelier screen, unhook the notices not from WordPress or this plugin.
	 */
	public function remove_foreign() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks the screen.
		if ( ! in_array( $page, self::PAGES, true ) ) {
			return;
		}
		$keep = array(
			wp_normalize_path( ABSPATH . 'wp-admin/' ),
			wp_normalize_path( ABSPATH . WPINC . '/' ),
			wp_normalize_path( AXELLCORE_ATELIERCLUB_PATH ),
		);
		foreach ( self::HOOKS as $hook ) {
			Callbacks::remove(
				$hook,
				static function ( $file ) use ( $keep ) {
					foreach ( $keep as $dir ) {
						if ( 0 === strpos( $file, $dir ) ) {
							return false;
						}
					}
					return true;
				}
			);
		}
	}
}
