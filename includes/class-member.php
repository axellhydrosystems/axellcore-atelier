<?php
/**
 * Registers the `aac_member` post type — one record per submitted Atelier
 * Club application (created by Members::create_from_params() on form submission).
 * Internal record-keeping only (not a public post type): admins review
 * submissions in wp-admin, nothing here is ever queried on the frontend.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `aac_member` post type registration.
 */
final class Member {

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'aac_member';

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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/**
	 * Screen IDs of the members list and single-record edit screens, which
	 * the DataViews/DataForms app replaces.
	 *
	 * @var array<string,string> Screen ID => body class.
	 */
	const ADMIN_SCREENS = array(
		'edit-aac_member' => 'aac-members-list',
		'aac_member'      => 'aac-members-edit',
	);

	/**
	 * Enqueue the DataViews/DataForms admin app on the members screens only.
	 */
	public function enqueue_admin() {
		$screen = get_current_screen();
		if ( ! $screen || ! isset( self::ADMIN_SCREENS[ $screen->id ] ) ) {
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
			'window.aacMembers = ' . wp_json_encode( $this->admin_config() ) . ';',
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
			array( 'wp-components' ),
			$asset['version']
		);
	}

	/**
	 * Config the admin app reads from window.aacMembers.
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

		return array(
			'listUrl' => admin_url( 'edit.php?post_type=' . self::POST_TYPE ),
			'editUrl' => admin_url( 'post.php?action=edit&post=' ),
			'states'  => $states,
			'atuacao' => Admin_Rest::ATUACAO_OPTIONS,
		);
	}

	/**
	 * Add the body class the app's CSS keys off on the members screens.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$screen = get_current_screen();
		if ( ! $screen || ! isset( self::ADMIN_SCREENS[ $screen->id ] ) ) {
			return $classes;
		}
		return $classes . ' ' . self::ADMIN_SCREENS[ $screen->id ];
	}

	/**
	 * Register the post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Members', 'axellcore-atelierclub' ),
					'singular_name'      => __( 'Member', 'axellcore-atelierclub' ),
					'add_new_item'       => __( 'Add New Member', 'axellcore-atelierclub' ),
					'edit_item'          => __( 'View Member', 'axellcore-atelierclub' ),
					'view_item'          => __( 'View Member', 'axellcore-atelierclub' ),
					'search_items'       => __( 'Search Members', 'axellcore-atelierclub' ),
					'not_found'          => __( 'No members found.', 'axellcore-atelierclub' ),
					'not_found_in_trash' => __( 'No members found in Trash.', 'axellcore-atelierclub' ),
					'all_items'          => __( 'All Members', 'axellcore-atelierclub' ),
				),
				'description'     => __( 'A submitted Atelier Axell Club application.', 'axellcore-atelierclub' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-groups',
				'menu_position'   => 26,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array(
					// Members only come from the public application form (Members::create_from_params).
					'create_posts' => 'do_not_allow',
				),
			)
		);
	}
}
