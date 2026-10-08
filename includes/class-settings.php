<?php
/**
 * Atelier → Settings: the plugin's options, in one option array.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page (Settings API) and access to the stored values.
 */
final class Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'axellcore_atelierclub_settings';

	/**
	 * Admin page slug.
	 */
	const PAGE = 'aa-settings';

	/**
	 * Settings group.
	 */
	const GROUP = 'axellcore_atelierclub_settings';

	/**
	 * Defaults: the plugin's behavior before each setting existed.
	 *
	 * @var array<string,mixed>
	 */
	const DEFAULTS = array(
		// New members wait for approval (role member_pending).
		'pending_on_create' => true,
		// The Atelier page; 0 = none.
		'page_id'           => 0,
	);

	/**
	 * The admin-post action of "Create Atelier page".
	 */
	const CREATE_ACTION = 'axellcore_atelierclub_create_page';

	/**
	 * Singleton instance.
	 *
	 * @var Settings|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Settings
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
		add_action( 'admin_menu', array( $this, 'register_page' ), 30 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::CREATE_ACTION, array( $this, 'handle_create_page' ) );
	}

	/**
	 * The Atelier page: the one chosen in Atelier > Settings, or null when
	 * none is (the setting may be empty: then no page is the Atelier page,
	 * nothing is guessed from slugs or IDs). Its slug can change; everything
	 * that needs the page asks here.
	 *
	 * @return \WP_Post|null
	 */
	public static function page() {
		$page_id = absint( self::get( 'page_id' ) );
		// Not get_post( 0 ): that returns the global post, any page being viewed.
		$page = $page_id ? get_post( $page_id ) : null;
		if ( $page instanceof \WP_Post && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
			return $page;
		}
		return null;
	}

	/**
	 * Whether the Atelier page was never set (activation then creates one).
	 * An empty choice saved later counts as set.
	 *
	 * @return bool
	 */
	public static function never_configured() {
		$values = get_option( self::OPTION, false );
		return ! is_array( $values ) || ! array_key_exists( 'page_id', $values );
	}

	/**
	 * Whether a post is the Atelier page.
	 *
	 * @param \WP_Post|int|null $post Post or ID.
	 * @return bool
	 */
	public static function is_page( $post ) {
		$post = $post ? get_post( $post ) : null;
		$page = self::page();
		return $post instanceof \WP_Post && $page && $post->ID === $page->ID;
	}

	/**
	 * Make a page the Atelier page.
	 *
	 * @param int $page_id Page ID.
	 */
	public static function set_page_id( $page_id ) {
		$values            = get_option( self::OPTION, array() );
		$values            = is_array( $values ) ? $values : array();
		$values['page_id'] = absint( $page_id );
		update_option( self::OPTION, $values );
	}

	/**
	 * One setting's value, or its default.
	 *
	 * @param string $key Setting key (see DEFAULTS).
	 * @return mixed
	 */
	public static function get( $key ) {
		$values = get_option( self::OPTION, array() );
		$values = is_array( $values ) ? $values : array();
		return array_key_exists( $key, $values ) ? $values[ $key ] : ( self::DEFAULTS[ $key ] ?? null );
	}

	/**
	 * Atelier → Settings, after Members and Export.
	 */
	public function register_page() {
		add_submenu_page(
			Member::ADMIN_PAGE,
			__( 'Atelier settings', 'axellcore-atelierclub' ),
			__( 'Settings', 'axellcore-atelierclub' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * The option, its section and fields.
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::DEFAULTS,
			)
		);

		add_settings_section( 'page', __( 'Page', 'axellcore-atelierclub' ), '__return_false', self::PAGE );

		add_settings_field(
			'page_id',
			__( 'Atelier page', 'axellcore-atelierclub' ),
			array( $this, 'render_page_id' ),
			self::PAGE,
			'page',
			array( 'label_for' => self::OPTION . '-page_id' )
		);

		add_settings_section( 'members', __( 'Members', 'axellcore-atelierclub' ), '__return_false', self::PAGE );

		add_settings_field(
			'pending_on_create',
			__( 'New members', 'axellcore-atelierclub' ),
			array( $this, 'render_pending_on_create' ),
			self::PAGE,
			'members',
			array( 'label_for' => self::OPTION . '-pending_on_create' )
		);
	}

	/**
	 * Keep only known keys, as booleans.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$page_id = absint( $input['page_id'] ?? 0 );
		return array(
			'pending_on_create' => ! empty( $input['pending_on_create'] ),
			'page_id'           => $page_id && 'page' === get_post_type( $page_id ) ? $page_id : 0,
		);
	}

	/**
	 * Select of pages, with links to edit and view the chosen one.
	 */
	public function render_page_id() {
		$page = self::page();
		wp_dropdown_pages(
			array(
				'name'              => esc_attr( self::OPTION ) . '[page_id]',
				'id'                => esc_attr( self::OPTION ) . '-page_id',
				'selected'          => $page ? (int) $page->ID : 0,
				'show_option_none'  => esc_html__( '— Select —', 'axellcore-atelierclub' ),
				'option_none_value' => '0',
				'post_status'       => array( 'publish', 'draft', 'private' ),
			)
		);
		if ( $page ) {
			printf(
				' <a href="%1$s">%2$s</a> | <a href="%3$s">%4$s</a>',
				esc_url( (string) get_edit_post_link( $page ) ),
				esc_html__( 'Edit', 'axellcore-atelierclub' ),
				esc_url( (string) get_permalink( $page ) ),
				esc_html__( 'View', 'axellcore-atelierclub' )
			);
		}
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'The page of the Atelier landing: its slug can change. With a block theme, use the Atelier Club template on it. Empty, no page is the Atelier page.', 'axellcore-atelierclub' )
		);
	}

	/**
	 * Checkbox: new members start as pending.
	 */
	public function render_pending_on_create() {
		printf(
			'<input type="hidden" name="%1$s[pending_on_create]" value="0"><label><input type="checkbox" id="%1$s-pending_on_create" name="%1$s[pending_on_create]" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_attr( self::OPTION ),
			checked( (bool) self::get( 'pending_on_create' ), true, false ),
			esc_html__( 'Create new members as pending', 'axellcore-atelierclub' ),
			esc_html__( 'When on, an application creates a pending member who waits for approval. When off, it creates the member already approved.', 'axellcore-atelierclub' )
		);
	}

	/**
	 * The settings form.
	 */
	public function render_page() {
		echo '<div class="wrap"><h1>' . esc_html__( 'Atelier settings', 'axellcore-atelierclub' ) . '</h1>';
		settings_errors( self::OPTION );
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form>';

		// Outside the settings form (forms cannot nest): a new page with the
		// bundled landing content.
		printf(
			'<form method="post" action="%1$s"><input type="hidden" name="action" value="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( self::CREATE_ACTION )
		);
		wp_nonce_field( self::CREATE_ACTION );
		submit_button( __( 'Create Atelier page', 'axellcore-atelierclub' ), 'secondary', 'submit', false );
		printf( '<p class="description">%s</p></form></div>', esc_html__( 'Creates a page with the Atelier landing content and makes it the Atelier page.', 'axellcore-atelierclub' ) );
	}

	/**
	 * "Create Atelier page": a new page from the bundled content, chosen as
	 * the Atelier page, then back to the settings with a notice.
	 */
	public function handle_create_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'axellcore-atelierclub' ), 403 );
		}
		check_admin_referer( self::CREATE_ACTION );
		$page_id = Activator::create_landing();
		if ( $page_id ) {
			self::set_page_id( $page_id );
			add_settings_error( self::OPTION, 'created', __( 'Atelier page created.', 'axellcore-atelierclub' ), 'success' );
		} else {
			add_settings_error( self::OPTION, 'not-created', __( 'Could not create the Atelier page.', 'axellcore-atelierclub' ) );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::PAGE,
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
