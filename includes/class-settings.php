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
	);

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
		$input = is_array( $input ) ? $input : array();
		return array(
			'pending_on_create' => ! empty( $input['pending_on_create'] ),
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
		echo '<div class="wrap"><h1>' . esc_html__( 'Atelier settings', 'axellcore-atelierclub' ) . '</h1><form method="post" action="options.php">';
		settings_fields( self::GROUP );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}
}
