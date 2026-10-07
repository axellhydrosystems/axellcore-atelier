<?php
/**
 * Design tokens of the Atelier (design/source :root) as theme.json presets,
 * added to the active theme's data so the styled sections reference presets
 * (var:preset|color|ink, var:preset|font-family|inter) instead of raw values,
 * and so they travel with the plugin to any install.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Palette and self-hosted font families.
 */
final class Design_Tokens {

	/**
	 * Colors of design/source :root, in its order.
	 */
	const PALETTE = array(
		'ink'       => array( 'Ink', '#0B0E12' ),
		'ink-2'     => array( 'Ink 2', '#10141B' ),
		'ink-3'     => array( 'Ink 3', '#171B23' ),
		'ink-warm'  => array( 'Ink warm', '#14100C' ),
		'ivory'     => array( 'Ivory', '#EFECE4' ),
		'ivory-2'   => array( 'Ivory 2', '#E5DFD1' ),
		'ivory-3'   => array( 'Ivory 3', '#D3CBB7' ),
		'line-iv'   => array( 'Line ivory', '#DED6C2' ),
		'stone'     => array( 'Stone', '#8A8172' ),
		'stone-dk'  => array( 'Stone dark', '#5F584C' ),
		'bronze'    => array( 'Bronze', '#B4996A' ),
		'bronze-2'  => array( 'Bronze 2', '#C6AC7E' ),
		'bronze-3'  => array( 'Bronze 3', '#D9C199' ),
		'amber'     => array( 'Amber', '#E6B27A' ),
		'line-dk'   => array( 'Line dark', 'rgba(239,236,228,0.10)' ),
		'line-dk-2' => array( 'Line dark 2', 'rgba(239,236,228,0.18)' ),
	);

	/**
	 * Font stacks of design/source (--serif, --sans).
	 */
	const FAMILIES = array(
		'cormorant-garamond' => array( 'Cormorant Garamond', '"Cormorant Garamond", "Times New Roman", Georgia, serif' ),
		'inter'              => array( 'Inter', '"Inter", -apple-system, "Segoe UI", system-ui, sans-serif' ),
	);

	/**
	 * Singleton instance.
	 *
	 * @var Design_Tokens|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return Design_Tokens
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Hook the theme.json filter.
	 */
	public function register_hooks() {
		add_filter( 'wp_theme_json_data_theme', array( $this, 'add_palette' ) );
		add_filter( 'wp_theme_json_data_default', array( $this, 'add_font_families' ) );
		add_action( 'init', array( $this, 'register_block_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_text_rendering' ) );
	}

	/**
	 * Page-level rules of design/source with no block attribute or theme.json
	 * setting: the text rendering of its body (without it text renders
	 * heavier on macOS) and the ink background of its html (a section whose
	 * height ends in a fraction of a pixel shows it in its last row).
	 */
	const TEXT_RENDERING_CSS = 'html{background:var(--wp--preset--color--ink)}body{-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}';

	/**
	 * Page templates of the Atelier pages.
	 */
	const TEMPLATES = array( 'atelier-section', Plugin::TEMPLATE_SLUG );

	/**
	 * Whether the current request (or the given post) uses an Atelier page
	 * template.
	 *
	 * @param \WP_Post|null $post Post, or null for the current request.
	 * @return bool
	 */
	public static function is_atelier_page( $post = null ) {
		if ( $post ) {
			return in_array( get_page_template_slug( $post ), self::TEMPLATES, true );
		}
		foreach ( self::TEMPLATES as $template ) {
			if ( is_page_template( $template ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The source's text rendering, only on Atelier pages.
	 */
	public function enqueue_text_rendering() {
		if ( ! self::is_atelier_page() ) {
			return;
		}
		wp_register_style( 'aa-text-rendering', false, array(), AXELLCORE_ATELIERCLUB_VERSION );
		wp_enqueue_style( 'aa-text-rendering' );
		wp_add_inline_style( 'aa-text-rendering', self::TEXT_RENDERING_CSS );
	}

	/**
	 * Block style variations for effects that have no block attribute
	 * (hover states, transitions, filters), chosen in the editor's Styles
	 * panel (no class typed by hand). Their CSS is src/block-styles/style.scss
	 * (built to build/block-styles/style-frontend.css):
	 * - "Primary" on buttons: label and inline arrow icon 14px apart, and the
	 *   .btn-primary hover;
	 * - "Bronze hover" on navigations: links turn bronze-2 on hover, no
	 *   underline, like .nav-links a;
	 * - "Ivory" on images: the dark logo rendered ivory, like .nav-logo img
	 *   (a duotone filter gets within 1 level, not equal);
	 * - "Visually hidden" on groups: off screen but read by screen readers
	 *   (the hero's page h1), like .visually-hidden;
	 * - "Pillars", "Protagonists" and "Promises" on lists: numbered grids
	 *   (the manifesto pillars, the protagonistas and the promessas items),
	 *   sharing one SCSS mixin.
	 *
	 * A block can have more than one style, so each row is block, name, label.
	 */
	public function register_block_styles() {
		$file = 'build/block-styles/style-frontend.css';
		if ( ! file_exists( AXELLCORE_ATELIERCLUB_PATH . $file ) ) {
			return;
		}
		$styles = array(
			array( 'core/button', 'primary', __( 'Primary', 'axellcore-atelierclub' ) ),
			array( 'core/navigation', 'bronze-hover', __( 'Bronze hover', 'axellcore-atelierclub' ) ),
			array( 'core/image', 'ivory', __( 'Ivory', 'axellcore-atelierclub' ) ),
			array( 'core/group', 'visually-hidden', __( 'Visually hidden', 'axellcore-atelierclub' ) ),
			array( 'core/paragraph', 'eyebrow', __( 'Eyebrow', 'axellcore-atelierclub' ) ),
			array( 'core/column', 'sticky-desktop', __( 'Sticky on desktop', 'axellcore-atelierclub' ) ),
			array( 'core/heading', 'chapter', __( 'Chapter', 'axellcore-atelierclub' ) ),
			array( 'core/list', 'pillars', __( 'Pillars', 'axellcore-atelierclub' ) ),
			array( 'core/list', 'protagonists', __( 'Protagonists', 'axellcore-atelierclub' ) ),
			array( 'core/list', 'promises', __( 'Promises', 'axellcore-atelierclub' ) ),
			array( 'core/cover', 'stone', __( 'Stone', 'axellcore-atelierclub' ) ),
		);
		foreach ( $styles as list( $block, $name, $label ) ) {
			register_block_style(
				$block,
				array(
					'name'  => $name,
					'label' => $label,
				)
			);
			// Loaded where the block renders, on the front and in the editor
			// (a style_handle on the variation is enqueued too late for a
			// block theme, whose template renders before wp_head).
			wp_enqueue_block_style(
				$block,
				array(
					'handle' => 'aa-block-styles',
					'src'    => AXELLCORE_ATELIERCLUB_URL . $file,
					'path'   => AXELLCORE_ATELIERCLUB_PATH . $file,
					'ver'    => (string) filemtime( AXELLCORE_ATELIERCLUB_PATH . $file ),
				)
			);
		}
	}

	/**
	 * Append the palette to the theme's own colors (a preset list given to
	 * update_with() replaces the theme's list, so the theme's entries are
	 * kept explicitly).
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme JSON data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function add_palette( $theme_json ) {
		$data    = $theme_json->get_data();
		$palette = $data['settings']['color']['palette'] ?? array();
		$palette = isset( $palette['theme'] ) ? $palette['theme'] : $palette;

		$own     = array_keys( self::PALETTE );
		$palette = array_values( array_filter( $palette, fn( $c ) => ! in_array( $c['slug'] ?? '', $own, true ) ) );
		foreach ( self::PALETTE as $slug => list( $name, $color ) ) {
			$palette[] = array(
				'slug'  => $slug,
				'name'  => $name,
				'color' => $color,
			);
		}

		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array( 'color' => array( 'palette' => $palette ) ),
			)
		);
	}

	/**
	 * Add the font families to the default origin, not the theme's: the Font
	 * Library can switch the theme's fonts off (user settings with an empty
	 * "theme" list), which would remove them too.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Default (core) JSON data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function add_font_families( $theme_json ) {
		$data     = $theme_json->get_data();
		$families = $data['settings']['typography']['fontFamilies'] ?? array();
		$families = isset( $families['default'] ) ? $families['default'] : $families;

		$own      = array_keys( self::FAMILIES );
		$families = array_values( array_filter( $families, fn( $f ) => ! in_array( $f['slug'] ?? '', $own, true ) ) );
		$faces    = $this->font_faces();
		foreach ( self::FAMILIES as $slug => list( $name, $stack ) ) {
			$families[] = array(
				'slug'       => $slug,
				'name'       => $name,
				'fontFamily' => $stack,
				'fontFace'   => array_values( array_filter( $faces, fn( $face ) => $face['fontFamily'] === $name ) ),
			);
		}

		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array( 'typography' => array( 'fontFamilies' => $families ) ),
			)
		);
	}

	/**
	 * Font faces from assets/fonts/font-faces.json (generated from the
	 * design's fonts.css), with absolute URLs to the plugin's woff2 files.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function font_faces() {
		$file = AXELLCORE_ATELIERCLUB_PATH . 'assets/fonts/font-faces.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}
		$faces = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $faces ) ) {
			return array();
		}
		foreach ( $faces as &$face ) {
			$face['src'] = array( AXELLCORE_ATELIERCLUB_URL . 'assets/fonts/' . basename( $face['src'] ) );
		}
		return $faces;
	}
}
