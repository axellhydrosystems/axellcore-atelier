<?php
/**
 * Members are WordPress users: an adesão submission creates a user with the
 * `member_pending` role (Members::create_from_params()), and the curadoria
 * approves it by switching the role to `member`. This class registers both
 * roles and the Members admin page (the DataViews/DataForms app over those
 * users, src/admin/members/).
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Member roles and admin page.
 */
final class Member {

	/**
	 * Role of a submitted application, waiting for the curadoria.
	 */
	const ROLE_PENDING = 'member_pending';

	/**
	 * Role of an approved member.
	 */
	const ROLE = 'member';

	/**
	 * Slug of the Members admin page (admin.php?page=members).
	 */
	const ADMIN_PAGE = 'members';

	/**
	 * Singleton instance.
	 *
	 * @var Member|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Member
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
		add_action( 'init', array( $this, 'register_roles' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/**
	 * Both member roles, with their status label.
	 *
	 * @return array<string,string> Role => label.
	 */
	public static function roles() {
		return array(
			self::ROLE_PENDING => __( 'Pending', 'axellcore-atelierclub' ),
			self::ROLE         => __( 'Member', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * Register the roles (add_role() stores them in the options), and
	 * rename one whose stored name is not the current label.
	 */
	public function register_roles() {
		$roles = array(
			self::ROLE_PENDING => array( __( 'Pending Member', 'axellcore-atelierclub' ), array() ),
			self::ROLE         => array( __( 'Member', 'axellcore-atelierclub' ), array( 'read' => true ) ),
		);
		foreach ( $roles as $role => list( $name, $caps ) ) {
			$current = get_role( $role );
			if ( $current && wp_roles()->role_names[ $role ] === $name ) {
				continue;
			}
			if ( $current ) {
				// Users keep the role (it is stored on them by slug).
				remove_role( $role );
			}
			add_role( $role, $name, $caps );
		}
	}

	/**
	 * The Atelier admin menu, whose first page is Members: the list, and
	 * one member with `&member=<id>`.
	 */
	public function register_admin_page() {
		// "Atelier" menu; its first item (same slug) is Members.
		add_menu_page(
			__( 'Members', 'axellcore-atelierclub' ),
			__( 'Atelier', 'axellcore-atelierclub' ),
			'list_users',
			self::ADMIN_PAGE,
			array( $this, 'render_admin_page' ),
			'dashicons-groups',
			26
		);
		add_submenu_page(
			self::ADMIN_PAGE,
			__( 'Members', 'axellcore-atelierclub' ),
			__( 'Members', 'axellcore-atelierclub' ),
			'list_users',
			self::ADMIN_PAGE,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Page shell; the app mounts after its heading.
	 */
	public function render_admin_page() {
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Members', 'axellcore-atelierclub' ) . '</h1>';
		if ( ! $this->current_member_id() && current_user_can( Members_Export::CAPABILITY ) ) {
			printf(
				' <a href="%s" class="page-title-action">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . Members_Export::PAGE ) ),
				esc_html__( 'Export', 'axellcore-atelierclub' )
			);
		}
		echo '<hr class="wp-header-end"></div>';
	}

	/**
	 * Whether the current screen is the Members page.
	 *
	 * @return bool
	 */
	private function is_admin_page() {
		$screen = get_current_screen();
		return $screen && 'toplevel_page_' . self::ADMIN_PAGE === $screen->id;
	}

	/**
	 * Member being viewed (`&member=<id>`), 0 on the list.
	 *
	 * @return int
	 */
	private function current_member_id() {
		return isset( $_GET['member'] ) ? absint( $_GET['member'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection.
	}

	/**
	 * Enqueue the DataViews/DataForms admin app on the Members page only.
	 */
	public function enqueue_admin() {
		if ( ! $this->is_admin_page() ) {
			return;
		}

		$asset_file = AXELLCORE_ATELIERCLUB_PATH . 'build/admin/members/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		$handle = 'axellcore-atelierclub-admin-members';
		wp_add_inline_script(
			'wp-api-fetch',
			'window.aaMembers = ' . wp_json_encode( $this->admin_config() ) . ';',
			'before'
		);
		wp_enqueue_script(
			$handle,
			AXELLCORE_ATELIERCLUB_URL . 'build/admin/members/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			$handle,
			AXELLCORE_ATELIERCLUB_URL . 'build/admin/members/style-index.css',
			array( 'wp-components', Assets::admin_dataviews_style() ),
			$asset['version']
		);
	}

	/**
	 * Config the admin app reads from window.aaMembers.
	 *
	 * @return array
	 */
	private function admin_config() {
		$states = array();
		foreach ( Locations::instance()->states() as $uf => $name ) {
			$states[] = array(
				'value' => $uf,
				'label' => $name,
			);
		}

		$options = static function ( array $map ) {
			return array_map(
				static function ( $value, $label ) {
					return array(
						'value' => $value,
						'label' => $label,
					);
				},
				array_keys( $map ),
				$map
			);
		};

		$page = admin_url( 'admin.php?page=' . self::ADMIN_PAGE );
		return array(
			'listUrl'      => $page,
			'editUrl'      => $page . '&member=',
			'memberId'     => $this->current_member_id(),
			'states'       => $states,
			'primaryFocus' => $options( Admin_Rest::PRIMARY_FOCUS_OPTIONS ),
			'statuses'     => $options( self::roles() ),
		);
	}

	/**
	 * Add the body class the app's CSS keys off on the Members page.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( ! $this->is_admin_page() ) {
			return $classes;
		}
		return $classes . ' ' . ( $this->current_member_id() ? 'aa-members-edit' : 'aa-members-list' );
	}
}
