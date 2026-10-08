<?php
/**
 * The Atelier admin screens, the users' screens (list, profile, user edit:
 * the member fields), Settings > General, the resellers' screens (list and
 * edit) and the block
 * editor without other plugins' and the theme's notices (promotions, rating
 * requests, onboarding):
 * WordPress's and this plugin's notices stay. In the block editor they
 * showed for a moment before the editor replaced them.
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
	 * Other screens (ids) without them: the users' list, profile and edit,
	 * and Settings > General.
	 */
	const SCREENS = array( 'users', 'profile', 'user-edit', 'options-general' );

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
	 * Whether the screen is one without other plugins' notices: the block
	 * editor, the users' screens, the resellers' list and edit.
	 *
	 * @param \WP_Screen|null $screen Current screen.
	 * @return bool
	 */
	private static function is_quiet_screen( $screen ) {
		if ( ! $screen instanceof \WP_Screen ) {
			return false;
		}
		return $screen->is_block_editor()
			|| in_array( $screen->id, self::SCREENS, true )
			|| Resellers::POST_TYPE === $screen->post_type;
	}

	/**
	 * On an Atelier screen or in the block editor, unhook the notices not
	 * from WordPress or this plugin.
	 */
	public function remove_foreign() {
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks the screen.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! in_array( $page, self::PAGES, true ) && ! self::is_quiet_screen( $screen ) ) {
			return;
		}
		$keep    = array(
			wp_normalize_path( ABSPATH . 'wp-admin/' ),
			wp_normalize_path( ABSPATH . 'wp-includes/' ),
			wp_normalize_path( AXELLCORE_ATELIERCLUB_PATH ),
		);
		$foreign = static function ( $file ) use ( $keep ) {
			foreach ( $keep as $dir ) {
				if ( 0 === strpos( $file, $dir ) ) {
					return false;
				}
			}
			return true;
		};
		foreach ( self::HOOKS as $hook ) {
			Callbacks::remove( $hook, $foreign );
		}
		// Some notice libraries also print from the footer (Essential Addons'
		// in the block editor): only callbacks named like notices, so other
		// plugins' footer scripts stay.
		Callbacks::remove(
			'admin_footer',
			static function ( $file, $callback ) use ( $foreign ) {
				return $foreign( $file ) && false !== stripos( Callbacks::name( $callback ), 'notice' );
			}
		);
	}
}
