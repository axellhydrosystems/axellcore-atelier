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
		add_action( 'load-post.php', array( $this, 'activate_in_editor' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'editor_settings' ), PHP_INT_MAX, 2 );
		add_action( 'enqueue_block_assets', array( $this, 'dequeue_foreign_editor_styles' ), PHP_INT_MAX );
	}

	/**
	 * Editing the Atelier page under a classic theme: the same block theme
	 * environment as its front end, so the canvas shows it as it renders.
	 */
	public function activate_in_editor() {
		$post = get_post( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks the editor's styles.
		if ( wp_is_block_theme() || ! self::is_atelier_post( $post ) ) {
			return;
		}
		$this->active = true;
		wp_clean_theme_json_cache();
		// Core's layout and margins for themes without theme.json (840px
		// blocks, 28px margins, 8px canvas padding): an empty style keeps
		// wp-edit-blocks' dependency on it satisfied.
		wp_deregister_style( 'wp-editor-classic-layout-styles' );
		wp_register_style( 'wp-editor-classic-layout-styles', false, array(), AXELLCORE_ATELIERCLUB_VERSION );
		// The page is edited here, not in a page builder: no "Edit with
		// Elementor" switch or other builders' editor assets.
		$this->remove_builder_output( 'enqueue_block_editor_assets' );
	}

	/**
	 * Whether a post is the Atelier landing page.
	 *
	 * @param \WP_Post|null $post Post.
	 * @return bool
	 */
	private static function is_atelier_post( $post ) {
		return $post instanceof \WP_Post && 'page' === $post->post_type && Activator::PAGE_SLUG === $post->post_name && 0 === (int) $post->post_parent;
	}

	/**
	 * The editor's settings for the Atelier page: layout support, as with a
	 * theme.json (otherwise groups get an inner container and no layout),
	 * and the canvas styles of the front end: the full global stylesheet
	 * and Twenty Twenty-Five's rules, without the Customizer CSS and the
	 * classic theme's editor styles.
	 *
	 * @param array                    $settings Editor settings.
	 * @param \WP_Block_Editor_Context $context  Editor context.
	 * @return array
	 */
	public function editor_settings( $settings, $context ) {
		if ( ! $this->active || ! isset( $context->post ) || ! self::is_atelier_post( $context->post ) ) {
			return $settings;
		}
		$settings['supportsLayout'] = true;
		// What core gives a block theme (wp_get_post_content_block_attributes()):
		// the layout and gap of the template's Post Content, for the canvas root.
		$attributes = self::post_content_attributes();
		if ( $attributes ) {
			$settings['postContentAttributes'] = $attributes;
		}

		$styles = array();
		foreach ( (array) ( $settings['styles'] ?? array() ) as $style ) {
			if ( empty( $style['isGlobalStyles'] ) ) {
				continue;
			}
			if ( 'theme' === ( $style['__unstableType'] ?? '' ) ) {
				$style['css'] = \WP_Theme_JSON_Resolver::get_merged_data()->get_stylesheet( array( 'styles' ), array( 'default', 'theme', 'custom' ) );
			}
			$styles[] = $style;
		}
		$styles[]           = array(
			'css'            => (string) file_get_contents( AXELLCORE_ATELIERCLUB_PATH . self::THEME_CSS ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				// The canvas shows the post alone (post-only mode) and gives
				// its root the theme's root padding; on the page the template's
				// Post Content has none.
				. '.is-root-container.has-global-padding{padding-left:0;padding-right:0}'
				. '.is-root-container.has-global-padding>.alignfull{margin-left:0;margin-right:0}'
				// Nor the room the editor adds below the last block.
				. ':root :where(.editor-styles-wrapper)::after{height:0}'
				// The editor makes every block position: relative, over the
				// hero footer's own position: absolute (its custom CSS has less
				// specificity): the hero is the cover at the top of the page.
				. '.is-root-container>.wp-block-cover:first-child .wp-block-cover__inner-container>.wp-block-group.has-custom-css:last-child{position:absolute}'
				// The text rendering the page gets from Design_Tokens.
				. Design_Tokens::TEXT_RENDERING_CSS,
			'__unstableType' => 'theme',
			'isGlobalStyles' => false,
		);
		$settings['styles'] = $styles;
		return $settings;
	}

	/**
	 * Attributes of the Post Content block in the plugin's page template.
	 *
	 * @return array|null
	 */
	private static function post_content_attributes() {
		$blocks = parse_blocks( (string) file_get_contents( AXELLCORE_ATELIERCLUB_PATH . 'templates/' . Plugin::TEMPLATE_SLUG . '.html' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		while ( $blocks ) {
			$block = array_shift( $blocks );
			if ( 'core/post-content' === $block['blockName'] ) {
				return $block['attrs'];
			}
			$blocks = array_merge( $block['innerBlocks'], $blocks );
		}
		return null;
	}

	/**
	 * In the editor of the Atelier page, the stylesheets of the theme and of
	 * other plugins stay out of the canvas (the editor's own scripts stay).
	 */
	public function dequeue_foreign_editor_styles() {
		if ( ! $this->active || ! is_admin() ) {
			return;
		}
		wp_dequeue_style( 'classic-theme-styles' );
		foreach ( wp_styles()->queue as $handle ) {
			if ( ! self::is_own( wp_styles(), $handle ) ) {
				wp_dequeue_style( $handle );
			}
		}
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
	 * Remove what the theme and page builders hook (Elementor Pro's popups
	 * on the page, whose stylesheets are dequeued, or Elementor's switch in
	 * the editor). Analytics and other plugins keep their output.
	 *
	 * @param string $hook Action name.
	 */
	private function remove_builder_output( $hook ) {
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
		Callbacks::remove(
			$hook,
			static function ( $file ) use ( $dirs ) {
				foreach ( $dirs as $dir ) {
					if ( 0 === strpos( $file, $dir ) ) {
						return true;
					}
				}
				return false;
			}
		);
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
