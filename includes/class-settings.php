<?php
/**
 * Atelier → Settings: the plugin's options, in one option array, on tabs as
 * WooCommerce's settings (General, Members, E-mails; the e-mails listed and
 * one section per e-mail).
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
		'pending_on_create'  => true,
		// Approved members log in and reset their password (pending never).
		'members_can_log_in' => false,
		// The Atelier page; 0 = none.
		'page_id'            => 0,
	);

	/**
	 * Tabs: slug => settings sections page (the Settings API page id).
	 */
	const TABS = array( 'general', 'members', 'emails' );

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
		add_filter( 'display_post_states', array( $this, 'post_state' ), 10, 2 );
	}

	/**
	 * "— Atelier Page" after the Atelier page's title in the pages list, as
	 * WordPress marks the front page.
	 *
	 * @param array<string,string> $states Post states.
	 * @param \WP_Post|mixed       $post   Post.
	 * @return array<string,string>
	 */
	public function post_state( $states, $post ) {
		if ( self::is_page( $post ) ) {
			$states['axellcore_atelierclub_page'] = __( 'Atelier Page', 'axellcore-atelierclub' );
		}
		return $states;
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

		add_settings_section( 'page', __( 'Page', 'axellcore-atelierclub' ), '__return_false', self::PAGE . '-general' );

		add_settings_field(
			'page_id',
			__( 'Atelier page', 'axellcore-atelierclub' ),
			array( $this, 'render_page_id' ),
			self::PAGE . '-general',
			'page',
			array( 'label_for' => self::OPTION . '-page_id' )
		);

		add_settings_section( 'members', __( 'Members', 'axellcore-atelierclub' ), '__return_false', self::PAGE . '-members' );

		add_settings_field(
			'pending_on_create',
			__( 'New members', 'axellcore-atelierclub' ),
			array( $this, 'render_pending_on_create' ),
			self::PAGE . '-members',
			'members',
			array( 'label_for' => self::OPTION . '-pending_on_create' )
		);

		add_settings_field(
			'members_can_log_in',
			__( 'Dashboard access', 'axellcore-atelierclub' ),
			array( $this, 'render_members_can_log_in' ),
			self::PAGE . '-members',
			'members',
			array( 'label_for' => self::OPTION . '-members_can_log_in' )
		);
	}

	/**
	 * The fields sent, over the values saved: each tab sends only its own
	 * fields, so saving one never resets another. An e-mail's subject,
	 * heading or text equal to its default in the site's language is not
	 * stored (empty), so it follows the translation.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$values = get_option( self::OPTION, array() );
		$values = is_array( $values ) ? $values : array();
		foreach ( array( 'pending_on_create', 'members_can_log_in' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$values[ $key ] = ! empty( $input[ $key ] );
			}
		}
		if ( array_key_exists( 'page_id', $input ) ) {
			$page_id           = absint( $input['page_id'] );
			$values['page_id'] = $page_id && 'page' === get_post_type( $page_id ) ? $page_id : 0;
		}
		foreach ( array_keys( Notifications::EMAILS ) as $email ) {
			$key = Notifications::setting( $email, 'enabled' );
			if ( array_key_exists( $key, $input ) ) {
				$values[ $key ] = ! empty( $input[ $key ] );
			}
			foreach ( Notifications::FIELDS as $field ) {
				$key = Notifications::setting( $email, $field );
				if ( ! array_key_exists( $key, $input ) || ! is_scalar( $input[ $key ] ) ) {
					continue;
				}
				$value          = self::clean_text( (string) $input[ $key ], 'body' === $field );
				$values[ $key ] = self::clean_text( Notifications::site_default( $email, $field ), 'body' === $field ) === $value ? '' : $value;
			}
		}
		$key = Notifications::setting( 'team_new', 'to' );
		if ( array_key_exists( $key, $input ) && is_scalar( $input[ $key ] ) ) {
			$values[ $key ] = self::clean_recipients( (string) $input[ $key ] );
		}
		// The e-mails' header: none (the brand in text), the site's logo or one chosen.
		// The default (the bundled logo, as a custom image) is not stored.
		if ( array_key_exists( 'email_logo', $input ) ) {
			$choice               = in_array( $input['email_logo'], array( 'none', 'site', 'custom' ), true ) ? $input['email_logo'] : 'custom';
			$values['email_logo'] = 'custom' === $choice ? '' : $choice;
		}
		if ( array_key_exists( 'email_logo_id', $input ) ) {
			$id                      = absint( $input['email_logo_id'] );
			$id                      = $id && wp_attachment_is_image( $id ) ? $id : 0;
			$values['email_logo_id'] = Notifications::default_logo_id() === $id ? 0 : $id;
		}
		foreach ( array( 'atelier_name', 'tagline' ) as $field ) {
			$key = 'email_' . $field;
			if ( array_key_exists( $key, $input ) && is_scalar( $input[ $key ] ) ) {
				$value          = self::clean_text( (string) $input[ $key ], false );
				$values[ $key ] = self::clean_text( Notifications::site_brand_default( $field ), false ) === $value ? '' : $value;
			}
		}
		return $values;
	}

	/**
	 * A text field as stored: one line, or several (line breaks as \n), no
	 * spaces at the ends.
	 *
	 * @param string $value     Text.
	 * @param bool   $multiline Several lines (textarea).
	 * @return string
	 */
	private static function clean_text( $value, $multiline ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		return trim( $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
	}

	/**
	 * The team's recipients: valid e-mails, comma-separated; the invalid ones
	 * are dropped and named in a notice.
	 *
	 * @param string $value Typed list.
	 * @return string
	 */
	private static function clean_recipients( $value ) {
		$valid   = array();
		$invalid = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $value ) ) ) as $email ) {
			if ( is_email( $email ) ) {
				$valid[] = sanitize_email( $email );
			} else {
				$invalid[] = $email;
			}
		}
		if ( $invalid && function_exists( 'add_settings_error' ) ) {
			/* translators: %s: the invalid e-mail addresses. */
			add_settings_error( self::OPTION, 'invalid-recipients', sprintf( __( 'Left out, not valid e-mail addresses: %s', 'axellcore-atelierclub' ), implode( ', ', $invalid ) ) );
		}
		return implode( ', ', array_unique( $valid ) );
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
	 * Checkbox: approved members can log in (Member::can_access()).
	 */
	public function render_members_can_log_in() {
		printf(
			'<input type="hidden" name="%1$s[members_can_log_in]" value="0"><label><input type="checkbox" id="%1$s-members_can_log_in" name="%1$s[members_can_log_in]" value="1"%2$s> %3$s</label><p class="description">%4$s</p>',
			esc_attr( self::OPTION ),
			checked( (bool) self::get( 'members_can_log_in' ), true, false ),
			esc_html__( 'Allow members to log in', 'axellcore-atelierclub' ),
			esc_html__( 'Approved members can log in and reset their password. Pending members never can.', 'axellcore-atelierclub' )
		);
	}

	/**
	 * The tab shown (general by default).
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks the tab.
		return in_array( $tab, self::TABS, true ) ? $tab : 'general';
	}

	/**
	 * The e-mail whose section is shown on the E-mails tab, '' for the list.
	 *
	 * @return string
	 */
	private static function current_section() {
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks the section.
		return isset( Notifications::EMAILS[ $section ] ) ? $section : '';
	}

	/**
	 * A tab's (and section's) address.
	 *
	 * @param string $tab     Tab.
	 * @param string $section E-mail section.
	 * @return string
	 */
	public static function url( $tab = 'general', $section = '' ) {
		$args = array(
			'page'    => self::PAGE,
			'tab'     => $tab,
			'section' => $section,
		);
		return add_query_arg( array_filter( $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * The preview of an e-mail (Notifications::preview()).
	 *
	 * @param string $key E-mail.
	 * @return string
	 */
	private static function preview_url( $key ) {
		$args = array(
			'action' => Notifications::PREVIEW_ACTION,
			'email'  => $key,
		);
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), Notifications::PREVIEW_ACTION );
	}

	/**
	 * The settings screen: the tabs, then the tab's form (the E-mails tab
	 * lists the e-mails; each one has its own section).
	 */
	public function render_page() {
		$tab     = self::current_tab();
		$section = 'emails' === $tab ? self::current_section() : '';
		$labels  = array(
			'general' => __( 'General', 'axellcore-atelierclub' ),
			'members' => __( 'Members', 'axellcore-atelierclub' ),
			'emails'  => __( 'E-mails', 'axellcore-atelierclub' ),
		);

		echo '<div class="wrap"><h1>' . esc_html__( 'Atelier settings', 'axellcore-atelierclub' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Settings sections', 'axellcore-atelierclub' ) . '">';
		foreach ( $labels as $slug => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( self::url( $slug ) ),
				$tab === $slug ? ' nav-tab-active' : '',
				$tab === $slug ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
		settings_errors( self::OPTION );

		if ( 'emails' === $tab && '' === $section ) {
			echo '<form method="post" action="options.php">';
			settings_fields( self::GROUP );
			$this->render_email_header_settings();
			submit_button();
			echo '</form>';
			$this->render_email_list();
			echo '</div>';
			return;
		}

		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		if ( 'emails' === $tab ) {
			$this->render_email_section( $section );
		} else {
			do_settings_sections( self::PAGE . '-' . $tab );
		}
		submit_button();
		echo '</form>';

		if ( 'general' === $tab ) {
			// Outside the settings form (forms cannot nest): a new page with the
			// bundled landing content.
			printf(
				'<form method="post" action="%1$s"><input type="hidden" name="action" value="%2$s">',
				esc_url( admin_url( 'admin-post.php' ) ),
				esc_attr( self::CREATE_ACTION )
			);
			wp_nonce_field( self::CREATE_ACTION );
			submit_button( __( 'Create Atelier page', 'axellcore-atelierclub' ), 'secondary', 'submit', false );
			printf( '<p class="description">%s</p></form>', esc_html__( 'Creates a page with the Atelier landing content and makes it the Atelier page.', 'axellcore-atelierclub' ) );
		}
		echo '</div>';
	}

	/**
	 * The e-mails' header (as WooCommerce's "Header image"): no logo (the
	 * brand in text), the theme's logo or an image chosen.
	 */
	private function render_email_header_settings() {
		wp_enqueue_media();
		$choice     = Notifications::logo_choice();
		$site_id    = (int) get_theme_mod( 'custom_logo' );
		$custom_id  = Notifications::logo_id();
		$default_id = Notifications::default_logo_id();
		$name       = static fn( $key ) => esc_attr( self::OPTION . '[' . $key . ']' );
		$thumb      = static function ( $id ) {
			$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : AXELLCORE_ATELIERCLUB_URL . Notifications::LOGO_FILE;
			return $url ? sprintf( '<img src="%s" alt="" style="display:block;max-width:200px;height:auto;margin:8px 0;background:#fff;padding:8px;border:1px solid #dcdcde;">', esc_url( $url ) ) : '';
		};
		$svg_note   = static function ( $id ) {
			return $id && 'image/svg+xml' === get_post_mime_type( $id )
				? '<p class="description">' . esc_html__( 'This image is an SVG: Gmail and other e-mail clients do not show SVG. Prefer a PNG.', 'axellcore-atelierclub' ) . '</p>'
				: '';
		};
		$brand      = static function ( $field ) {
			$saved = (string) self::get( 'email_' . $field );
			return '' !== $saved ? $saved : Notifications::site_brand_default( $field );
		};

		printf( '<h2>%s</h2>', esc_html__( 'E-mail header', 'axellcore-atelierclub' ) );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row"><label for="aa-email-atelier-name">%1$s</label></th><td><input type="text" class="regular-text" id="aa-email-atelier-name" name="%2$s" value="%3$s"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Atelier name', 'axellcore-atelierclub' ),
			$name( 'email_atelier_name' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
			esc_attr( Notifications::atelier_name() ),
			/* translators: %s: the placeholder, {atelier_name}. */
			esc_html( sprintf( __( 'Used by the %s placeholder and as the header\'s brand in text.', 'axellcore-atelierclub' ), '{atelier_name}' ) )
		);
		printf(
			'<tr><th scope="row"><label for="aa-email-tagline">%1$s</label></th><td><input type="text" class="regular-text" id="aa-email-tagline" name="%2$s" value="%3$s"><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Description', 'axellcore-atelierclub' ),
			$name( 'email_tagline' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
			esc_attr( $brand( 'tagline' ) ),
			esc_html__( 'Below the name, when the header has no logo.', 'axellcore-atelierclub' )
		);
		echo '<tr><th scope="row">' . esc_html__( 'Logo', 'axellcore-atelierclub' ) . '</th><td><fieldset class="aa-email-logo">';
		printf( '<legend class="screen-reader-text">%s</legend>', esc_html__( 'Logo', 'axellcore-atelierclub' ) );

		// None: the brand in text.
		printf(
			'<p><label><input type="radio" name="%1$s" value="none"%2$s> %3$s</label></p>',
			$name( 'email_logo' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
			checked( $choice, 'none', false ),
			esc_html__( 'None: the brand in text', 'axellcore-atelierclub' )
		);

		// The theme's logo, when there is one.
		printf(
			'<p><label><input type="radio" name="%1$s" value="site"%2$s%3$s> %4$s</label></p><div style="margin:0 0 12px 24px">%5$s</div>',
			$name( 'email_logo' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
			checked( $choice, 'site', false ),
			disabled( ! $site_id, true, false ),
			esc_html__( 'Use the site\'s logo', 'axellcore-atelierclub' ),
			$site_id ? $thumb( $site_id ) . $svg_note( $site_id ) : '<p class="description">' . esc_html__( 'The theme has no logo (Appearance > Customize > Site Identity).', 'axellcore-atelierclub' ) . '</p>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped above.
		);

		// An image chosen in the media library.
		printf(
			'<p><label><input type="radio" name="%1$s" value="custom"%2$s> %3$s</label></p><div class="aa-email-logo-custom" style="margin:0 0 4px 24px"><input type="hidden" name="%4$s" id="aa-email-logo-id" value="%5$d" data-default="%11$d" data-default-url="%12$s"><div id="aa-email-logo-preview">%6$s</div>%7$s<p><button type="button" class="button" id="aa-email-logo-choose">%8$s</button> <button type="button" class="button-link" id="aa-email-logo-remove"%9$s>%10$s</button></p></div>',
			$name( 'email_logo' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
			checked( $choice, 'custom', false ),
			esc_html__( 'Image (by default, the Atelier logo)', 'axellcore-atelierclub' ),
			$name( 'email_logo_id' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
			absint( $custom_id ),
			$thumb( $custom_id ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped above.
			$svg_note( $custom_id ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped above.
			esc_html__( 'Choose image', 'axellcore-atelierclub' ),
			$custom_id !== $default_id ? '' : ' hidden',
			esc_html__( 'Use the default', 'axellcore-atelierclub' ),
			absint( $default_id ),
			esc_url( $default_id ? (string) wp_get_attachment_image_url( $default_id, 'medium' ) : AXELLCORE_ATELIERCLUB_URL . Notifications::LOGO_FILE )
		);
		echo '<p class="description">' . esc_html__( 'Shown at the top of every e-mail, up to 200px wide. A PNG works in every e-mail client.', 'axellcore-atelierclub' ) . '</p>';
		echo '</fieldset></td></tr></tbody></table>';
		?>
		<script>
		( function () {
			var choose = document.getElementById( 'aa-email-logo-choose' );
			var remove = document.getElementById( 'aa-email-logo-remove' );
			var field = document.getElementById( 'aa-email-logo-id' );
			var preview = document.getElementById( 'aa-email-logo-preview' );
			var radio = document.querySelector( 'input[type=radio][value=custom][name$="[email_logo]"]' );
			var frame;
			choose.addEventListener( 'click', function () {
				if ( ! frame ) {
					frame = wp.media( { title: choose.textContent, library: { type: 'image' }, multiple: false } );
					frame.on( 'select', function () {
						var image = frame.state().get( 'selection' ).first().toJSON();
						var size = ( image.sizes && ( image.sizes.medium || image.sizes.full ) ) || image;
						field.value = image.id;
						preview.innerHTML = '';
						var img = document.createElement( 'img' );
						img.src = size.url;
						img.alt = '';
						img.style.cssText = 'display:block;max-width:200px;height:auto;margin:8px 0;background:#fff;padding:8px;border:1px solid #dcdcde;';
						preview.appendChild( img );
						remove.hidden = false;
						radio.checked = true;
					} );
				}
				frame.open();
			} );
			// Back to the bundled logo.
			remove.addEventListener( 'click', function () {
				field.value = field.dataset.default;
				var img = document.createElement( 'img' );
				img.src = field.dataset.defaultUrl;
				img.alt = '';
				img.style.cssText = 'display:block;max-width:200px;height:auto;margin:8px 0;background:#fff;padding:8px;border:1px solid #dcdcde;';
				preview.innerHTML = '';
				preview.appendChild( img );
				remove.hidden = true;
				radio.checked = true;
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * The E-mails tab: the e-mails, as WooCommerce lists its own.
	 */
	private function render_email_list() {
		printf( '<p>%s</p>', esc_html__( 'E-mails sent by the Atelier. Each one is on until turned off; manage one to edit its subject, heading and text.', 'axellcore-atelierclub' ) );
		echo '<table class="widefat striped aa-emails"><thead><tr>';
		printf(
			'<th scope="col">%1$s</th><th scope="col">%2$s</th><th scope="col">%3$s</th><th scope="col"><span class="screen-reader-text">%4$s</span></th>',
			esc_html__( 'E-mail', 'axellcore-atelierclub' ),
			esc_html__( 'Recipient', 'axellcore-atelierclub' ),
			esc_html__( 'Status', 'axellcore-atelierclub' ),
			esc_html__( 'Actions', 'axellcore-atelierclub' )
		);
		echo '</tr></thead><tbody>';
		foreach ( Notifications::titles() as $key => list( $title, $description ) ) {
			$url       = self::url( 'emails', $key );
			$recipient = 'team' === Notifications::EMAILS[ $key ] ? implode( ', ', Notifications::team_recipients() ) : __( 'Member', 'axellcore-atelierclub' );
			$enabled   = Notifications::enabled( $key );
			printf(
				'<tr><td><a href="%1$s"><strong>%2$s</strong></a><p class="description">%3$s</p></td><td>%4$s</td><td>%5$s</td><td><a class="button" href="%1$s">%6$s</a></td></tr>',
				esc_url( $url ),
				esc_html( $title ),
				esc_html( $description ),
				esc_html( $recipient ),
				$enabled ? esc_html__( 'Enabled', 'axellcore-atelierclub' ) : esc_html__( 'Disabled', 'axellcore-atelierclub' ),
				esc_html__( 'Manage', 'axellcore-atelierclub' )
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * One e-mail's settings: on/off, recipients (the team's), subject,
	 * heading and text, prefilled with the default in the site's language.
	 *
	 * @param string $key E-mail.
	 */
	private function render_email_section( $key ) {
		$titles = Notifications::titles();
		$name   = static fn( $field ) => esc_attr( self::OPTION . '[' . Notifications::setting( $key, $field ) . ']' );
		$id     = static fn( $field ) => esc_attr( self::OPTION . '-' . Notifications::setting( $key, $field ) );
		$value  = static function ( $field ) use ( $key ) {
			$saved = (string) self::get( Notifications::setting( $key, $field ) );
			return '' !== $saved ? $saved : Notifications::site_default( $key, $field );
		};

		printf(
			'<p><a href="%1$s">&larr; %2$s</a></p><h2>%3$s</h2><p>%4$s</p>',
			esc_url( self::url( 'emails' ) ),
			esc_html__( 'E-mails', 'axellcore-atelierclub' ),
			esc_html( $titles[ $key ][0] ),
			esc_html( $titles[ $key ][1] )
		);
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="%2$s" value="0"><label><input type="checkbox" id="%3$s" name="%2$s" value="1"%4$s> %5$s</label></td></tr>',
			esc_html__( 'Enable/Disable', 'axellcore-atelierclub' ),
			$name( 'enabled' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
			$id( 'enabled' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $id.
			checked( Notifications::enabled( $key ), true, false ),
			esc_html__( 'Enable this e-mail', 'axellcore-atelierclub' )
		);
		if ( 'team' === Notifications::EMAILS[ $key ] ) {
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="%1$s" name="%3$s" value="%4$s" placeholder="%5$s"><p class="description">%6$s</p></td></tr>',
				$id( 'to' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $id.
				esc_html__( 'Recipients', 'axellcore-atelierclub' ),
				$name( 'to' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
				esc_attr( (string) self::get( Notifications::setting( $key, 'to' ) ) ),
				esc_attr( (string) get_option( 'admin_email' ) ),
				esc_html__( 'One or more e-mail addresses, separated by commas. Empty, the site\'s admin e-mail.', 'axellcore-atelierclub' )
			);
		}
		foreach ( array(
			'subject' => __( 'Subject', 'axellcore-atelierclub' ),
			'heading' => __( 'E-mail heading', 'axellcore-atelierclub' ),
		) as $field => $label ) {
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" class="large-text" id="%1$s" name="%3$s" value="%4$s"></td></tr>',
				$id( $field ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $id.
				esc_html( $label ),
				$name( $field ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
				esc_attr( $value( $field ) )
			);
		}
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><textarea class="large-text" rows="12" id="%1$s" name="%3$s" aria-describedby="%1$s-help">%4$s</textarea><p class="description" id="%1$s-help">%5$s</p></td></tr>',
			$id( 'body' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $id.
			esc_html__( 'Text', 'axellcore-atelierclub' ),
			$name( 'body' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by $name.
			esc_textarea( $value( 'body' ) ),
			esc_html__( 'Plain text: a blank line starts a paragraph and addresses become links. Sent as an HTML e-mail in the Atelier\'s colors.', 'axellcore-atelierclub' )
		);
		echo '<tr><th scope="row">' . esc_html__( 'Placeholders', 'axellcore-atelierclub' ) . '</th><td><ul class="aa-placeholders">';
		foreach ( Notifications::placeholder_help() as $tag => $meaning ) {
			printf( '<li><code>%1$s</code> %2$s</li>', esc_html( $tag ), esc_html( $meaning ) );
		}
		echo '</ul></td></tr>';
		printf(
			'<tr><th scope="row">%1$s</th><td><a href="%2$s" target="_blank" rel="noopener">%3$s</a><p class="description">%4$s</p></td></tr>',
			esc_html__( 'Preview', 'axellcore-atelierclub' ),
			esc_url( self::preview_url( $key ) ),
			esc_html__( 'Open the preview', 'axellcore-atelierclub' ),
			esc_html__( 'The saved e-mail, with a sample member.', 'axellcore-atelierclub' )
		);
		echo '</tbody></table>';
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
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', self::url( 'general' ) ) );
		exit;
	}
}
