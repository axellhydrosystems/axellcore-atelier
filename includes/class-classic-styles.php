<?php
/**
 * Block theme styles for the Atelier page under a classic theme.
 *
 * The page is built for a block theme (the bases were approved on Twenty
 * Twenty-Five). Under a classic theme without theme.json WordPress renders
 * blocks in a reduced mode: no layout settings (contentSize, wideSize, root
 * padding), no blockGap (so not even a block's own gap is printed), only the
 * base layout rules, and an inner container restored in groups and images.
 * The theme's and page builders' stylesheets also style the page. On the
 * Atelier page only, this class gives WordPress Twenty Twenty-Five's
 * theme.json and the global styles saved in its Site Editor
 * (content/global-styles.json, from bin/export-content.sh) in place of the
 * site's, renders blocks as for a theme with theme.json, prints the layout
 * rules the reduced mode leaves out and dequeues every stylesheet and script
 * that isn't WordPress's or this plugin's. Every other page of the site is
 * left as it is.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block theme environment for Classic_Template.
 */
final class Classic_Styles {

	/**
	 * Twenty Twenty-Five 1.5's theme.json, without templates and font files.
	 */
	const THEME_JSON = 'templates/atelier/block-theme.json';

	/**
	 * Twenty Twenty-Five 1.5's front-end style.css rules.
	 */
	const THEME_CSS = 'templates/atelier/block-theme.css';

	/**
	 * Global styles saved in the block theme's Site Editor (user origin).
	 */
	const USER_JSON = 'content/global-styles.json';

	/**
	 * Style handle of the rules printed here.
	 */
	const HANDLE = 'aa-block-theme';

	/**
	 * Singleton instance.
	 *
	 * @var Classic_Styles|null
	 */
	private static $instance = null;

	/**
	 * Whether this request renders the Atelier page under a classic theme.
	 *
	 * @var bool
	 */
	private $active = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return Classic_Styles
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
		add_action( 'wp', array( $this, 'activate' ) );
		add_filter( 'wp_theme_json_data_theme', array( $this, 'theme_json' ), 5 );
		add_filter( 'wp_theme_json_data_user', array( $this, 'user_json' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), PHP_INT_MAX );
		add_action( 'wp_print_styles', array( $this, 'dequeue_foreign' ), PHP_INT_MAX );
		add_action( 'wp_print_footer_scripts', array( $this, 'dequeue_foreign' ), 1 );
	}

	/**
	 * Once the query is known, switch on for the Atelier page and drop the
	 * theme.json data computed before, so the filter below is applied.
	 */
	public function activate() {
		$this->active = Classic_Template::is_active();
		if ( $this->active ) {
			wp_clean_theme_json_cache();
			remove_filter( 'render_block_core/group', 'wp_restore_group_inner_container' );
			remove_filter( 'render_block_core/image', 'wp_restore_image_outer_container' );
			foreach ( array( 'wp_body_open', 'wp_footer' ) as $hook ) {
				$this->remove_builder_output( $hook );
			}
		}
	}

	/**
	 * Remove what the theme and page builders print on a hook (Elementor
	 * Pro's popups, for example): their stylesheets are dequeued, so it would
	 * show unstyled. Analytics and other plugins keep their output.
	 *
	 * @param string $hook Action name.
	 */
	private function remove_builder_output( $hook ) {
		global $wp_filter;
		if ( empty( $wp_filter[ $hook ] ) ) {
			return;
		}
		/**
		 * Plugin folder prefixes of page builders whose output is left out of
		 * the Atelier page under a classic theme.
		 *
		 * @param string[] $prefixes Default Elementor, Essential Addons and JetPlugins.
		 */
		$prefixes = (array) apply_filters( 'axellcore_atelierclub_classic_builder_plugins', array( 'elementor', 'essential-addons-for-elementor', 'jet-' ) );
		$dirs     = array( wp_normalize_path( get_template_directory() ) . '/', wp_normalize_path( get_stylesheet_directory() ) . '/' );
		foreach ( $prefixes as $prefix ) {
			$dirs[] = wp_normalize_path( WP_PLUGIN_DIR ) . '/' . $prefix;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$file = self::callback_file( $callback['function'] );
				foreach ( $dirs as $dir ) {
					if ( '' !== $file && 0 === strpos( $file, $dir ) ) {
						remove_action( $hook, $callback['function'], $priority );
						break;
					}
				}
			}
		}
	}

	/**
	 * File a callback is defined in, or '' when it can't be told.
	 *
	 * @param callable|mixed $callback Callback.
	 * @return string
	 */
	private static function callback_file( $callback ) {
		try {
			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$reflection = new \ReflectionMethod( $callback );
			} elseif ( $callback instanceof \Closure || is_string( $callback ) ) {
				$reflection = new \ReflectionFunction( $callback );
			} else {
				return '';
			}
		} catch ( \ReflectionException $e ) {
			return '';
		}
		return wp_normalize_path( (string) $reflection->getFileName() );
	}

	/**
	 * Whether this request renders the Atelier page under a classic theme.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->active;
	}

	/**
	 * Twenty Twenty-Five's settings and styles as the theme's theme.json.
	 * Early (priority 5), so Design_Tokens adds the plugin's palette on top
	 * of it, as it does on top of a block theme's.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme JSON data object.
	 * @return \WP_Theme_JSON_Data
	 */
	public function theme_json( $theme_json ) {
		if ( ! $this->active ) {
			return $theme_json;
		}
		$data = json_decode( (string) file_get_contents( AXELLCORE_ATELIERCLUB_PATH . self::THEME_JSON ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $data ) ? $theme_json->update_with( $data ) : $theme_json;
	}

	/**
	 * The block theme's Site Editor styles in place of the site's (those
	 * belong to the classic theme). Font files are referenced by their path
	 * in this plugin.
	 *
	 * @param \WP_Theme_JSON_Data $user_json User origin data.
	 * @return \WP_Theme_JSON_Data
	 */
	public function user_json( $user_json ) {
		if ( ! $this->active ) {
			return $user_json;
		}
		$data = json_decode( (string) file_get_contents( AXELLCORE_ATELIERCLUB_PATH . self::USER_JSON ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $data ) ) {
			return $user_json;
		}
		foreach ( $data['settings']['typography']['fontFamilies'] ?? array() as $origin => $families ) {
			foreach ( (array) $families as $f => $family ) {
				foreach ( $family['fontFace'] ?? array() as $i => $face ) {
					$srcs = array_map(
						static function ( $src ) {
							return 0 === strpos( (string) $src, 'assets/' ) ? AXELLCORE_ATELIERCLUB_URL . $src : $src;
						},
						(array) $face['src']
					);
					$data['settings']['typography']['fontFamilies'][ $origin ][ $f ]['fontFace'][ $i ]['src'] = is_array( $face['src'] ) ? $srcs : $srcs[0];
				}
			}
		}
		return new \WP_Theme_JSON_Data( $data, 'custom' );
	}

	/**
	 * Global styles as wp_enqueue_global_styles() prints them for a block
	 * theme: in the head (the page is rendered before wp_head(), so every
	 * block is known), with the layout and alignment rules the classic
	 * theme mode leaves out, then Twenty Twenty-Five's style.css rules.
	 * The site's Customizer CSS is for the classic theme's pages: left out.
	 */
	public function enqueue() {
		if ( ! $this->active ) {
			return;
		}
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_head', 'wp_custom_css_cb', 101 );

		$origins = array( 'default', 'theme', 'custom' );
		$tree    = \WP_Theme_JSON_Resolver::get_merged_data();
		add_filter( 'wp_theme_json_get_style_nodes', 'wp_filter_out_block_nodes' );
		$stylesheet = $tree->get_stylesheet( array( 'variables' ), $origins )
			. $tree->get_stylesheet( array( 'styles', 'presets' ), $origins )
			. wp_get_global_stylesheet( array( 'custom-css' ) );
		remove_filter( 'wp_theme_json_get_style_nodes', 'wp_filter_out_block_nodes' );

		wp_register_style( 'global-styles', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline only, as core.
		wp_add_inline_style( 'global-styles', $stylesheet );
		wp_enqueue_style( 'global-styles' );
		wp_add_global_styles_for_blocks();

		wp_register_style( self::HANDLE, false, array( 'global-styles' ), AXELLCORE_ATELIERCLUB_VERSION );
		wp_add_inline_style( self::HANDLE, (string) file_get_contents( AXELLCORE_ATELIERCLUB_PATH . self::THEME_CSS ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		wp_enqueue_style( self::HANDLE );

		$this->dequeue_foreign();
	}

	/**
	 * Dequeue the stylesheets and scripts of the theme and of other plugins.
	 * Runs again before printing, for the ones enqueued late.
	 */
	public function dequeue_foreign() {
		if ( ! $this->active ) {
			return;
		}
		wp_dequeue_style( 'classic-theme-styles' );
		foreach ( array( wp_styles(), wp_scripts() ) as $dependencies ) {
			foreach ( $dependencies->queue as $handle ) {
				if ( ! self::is_own( $dependencies, $handle ) ) {
					$dependencies->dequeue( $handle );
				}
			}
		}
	}

	/**
	 * Whether a handle is WordPress's or this plugin's: its file (or, for an
	 * inline-only handle, the files of its dependencies) under wp-includes,
	 * wp-admin or this plugin.
	 *
	 * @param \WP_Dependencies $dependencies Styles or scripts.
	 * @param string           $handle       Handle.
	 * @return bool
	 */
	private static function is_own( $dependencies, $handle ) {
		$item = $dependencies->registered[ $handle ] ?? null;
		if ( ! $item ) {
			return false;
		}
		if ( ! $item->src ) {
			return self::is_core_inline( $handle );
		}
		$src = (string) $item->src;
		foreach ( array( includes_url(), admin_url(), '/wp-includes/', '/wp-admin/', AXELLCORE_ATELIERCLUB_URL ) as $prefix ) {
			if ( 0 === strpos( $src, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Inline-only handles kept: WordPress's block and global styles and this
	 * plugin's (they all use one of these prefixes).
	 *
	 * @param string $handle Handle.
	 * @return bool
	 */
	private static function is_core_inline( $handle ) {
		foreach ( array( 'wp-', 'global-styles', 'core-block-supports', 'block-style-variation-styles', 'axell-', 'aa-', 'axellcore-' ) as $prefix ) {
			if ( 0 === strpos( $handle, $prefix ) ) {
				return true;
			}
		}
		return false;
	}
}
