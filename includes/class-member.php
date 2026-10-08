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
	 * Action that approves pending members on the users' list (row and bulk).
	 */
	const APPROVE_ACTION = 'aa-approve';

	/**
	 * Query arg with how many were approved, for the notice.
	 */
	const APPROVED_ARG = 'aa-approved';

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
		// Users' list: approve pending members (row action and bulk action).
		add_filter( 'user_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-users', array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_approve' ), 10, 3 );
		add_action( 'load-users.php', array( $this, 'handle_approve' ) );
		add_action( 'admin_notices', array( $this, 'approved_notice' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
	}

	/**
	 * Approve a pending member: the member role in place of the pending one
	 * (the status, as Atelier > Members sets it).
	 *
	 * @param int $user_id User ID.
	 * @return bool Whether the user was pending and is now a member.
	 */
	public static function approve( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user instanceof \WP_User || ! in_array( self::ROLE_PENDING, (array) $user->roles, true ) ) {
			return false;
		}
		$user->set_role( self::ROLE );
		return true;
	}

	/**
	 * Whether the current user can approve a user (change its role).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function can_approve( $user_id ) {
		return current_user_can( 'promote_user', $user_id ) && current_user_can( 'edit_user', $user_id );
	}

	/**
	 * "Approve" among a pending member's actions on the users' list.
	 *
	 * @param array<string,string> $actions Row actions.
	 * @param \WP_User             $user    User of the row.
	 * @return array<string,string>
	 */
	public function row_actions( $actions, $user ) {
		if ( ! $user instanceof \WP_User || ! in_array( self::ROLE_PENDING, (array) $user->roles, true ) || ! self::can_approve( $user->ID ) ) {
			return $actions;
		}
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::APPROVE_ACTION,
					'user'   => $user->ID,
				),
				admin_url( 'users.php' )
			),
			self::APPROVE_ACTION . '-user_' . $user->ID
		);

		$actions[ self::APPROVE_ACTION ] = sprintf(
			'<a href="%1$s" aria-label="%2$s">%3$s</a>',
			esc_url( $url ),
			/* translators: %s: user's display name. */
			esc_attr( sprintf( __( 'Approve %s', 'axellcore-atelierclub' ), $user->display_name ) ),
			esc_html__( 'Approve', 'axellcore-atelierclub' )
		);
		return $actions;
	}

	/**
	 * "Approve" among the users' list bulk actions.
	 *
	 * @param array<string,string> $actions Bulk actions.
	 * @return array<string,string>
	 */
	public function bulk_actions( $actions ) {
		if ( current_user_can( 'promote_users' ) ) {
			$actions[ self::APPROVE_ACTION ] = __( 'Approve', 'axellcore-atelierclub' );
		}
		return $actions;
	}

	/**
	 * Approve the pending members chosen (WordPress checked the bulk nonce).
	 *
	 * @param string $redirect Where WordPress goes back.
	 * @param string $action   Bulk action.
	 * @param int[]  $user_ids Users chosen.
	 * @return string
	 */
	public function handle_bulk_approve( $redirect, $action, $user_ids ) {
		if ( self::APPROVE_ACTION !== $action ) {
			return $redirect;
		}
		$approved = 0;
		foreach ( (array) $user_ids as $user_id ) {
			if ( self::can_approve( (int) $user_id ) && self::approve( (int) $user_id ) ) {
				++$approved;
			}
		}
		return add_query_arg( self::APPROVED_ARG, $approved, $redirect );
	}

	/**
	 * The row action's link: approve one member and go back to the list.
	 */
	public function handle_approve() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is checked below.
		if ( ! isset( $_GET['action'], $_GET['user'] ) || self::APPROVE_ACTION !== $_GET['action'] ) {
			return;
		}
		$user_id = absint( $_GET['user'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		check_admin_referer( self::APPROVE_ACTION . '-user_' . $user_id );
		if ( ! self::can_approve( $user_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this user.', 'axellcore-atelierclub' ), 403 );
		}
		$approved = self::approve( $user_id ) ? 1 : 0;
		$back     = wp_get_referer();
		wp_safe_redirect( add_query_arg( self::APPROVED_ARG, $approved, remove_query_arg( array( 'action', 'user', '_wpnonce' ), $back ? $back : admin_url( 'users.php' ) ) ) );
		exit;
	}

	/**
	 * After approving: how many, on the users' list.
	 */
	public function approved_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only a count to show.
		if ( ! $screen || 'users' !== $screen->id || ! isset( $_GET[ self::APPROVED_ARG ] ) ) {
			return;
		}
		$approved = absint( $_GET[ self::APPROVED_ARG ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $approved ) {
			return;
		}
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				1 === $approved
					? __( 'Member approved.', 'axellcore-atelierclub' )
					/* translators: %d: number of members approved. */
					: sprintf( _n( '%d member approved.', '%d members approved.', $approved, 'axellcore-atelierclub' ), $approved )
			)
		);
	}

	/**
	 * The count leaves the address once shown (as WordPress's own).
	 *
	 * @param string[] $args Query args removed from the address.
	 * @return string[]
	 */
	public function removable_query_args( $args ) {
		$args[] = self::APPROVED_ARG;
		return $args;
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
		// Translations of its __() strings: the plugin's languages/ or the installed language pack.
		wp_set_script_translations( $handle, 'axellcore-atelierclub' );
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
