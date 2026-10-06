<?php
/**
 * Registers a plugin-owned FSE block template ("Atelier — Blank Canvas") so the
 * landing page renders with zero theme chrome (no header/footer template parts)
 * while remaining fully visible and editable in the Site Editor's Templates list.
 *
 * Deliberately NOT a classic `Template Name:` PHP file wired through
 * `template_include` — this uses core's register_block_template() (WP 6.7+),
 * confirmed present in wp-includes/block-template.php on this install.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FSE template registration.
 */
final class Template_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var Template_Loader|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Template_Loader
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
		add_action( 'init', array( $this, 'register_template' ) );
		add_filter( 'get_block_templates', array( $this, 'reindex_block_templates' ) );
		add_filter( 'wp_theme_json_data_theme', array( $this, 'enable_position_sticky' ) );
	}

	/**
	 * Register the "Atelier — Blank Canvas" block template.
	 */
	public function register_template() {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}

		$template_path = AXELLCORE_ATELIERCLUB_PATH . 'templates/atelier-club.html';

		if ( ! file_exists( $template_path ) ) {
			return;
		}

		register_block_template(
			Plugin::TEMPLATE_NAME,
			array(
				'title'       => __( 'Atelier — Blank Canvas', 'axellcore-atelierclub' ),
				'description' => __( 'Self-contained canvas for the Atelier Axell Club landing page. No header/footer template parts — the page content renders alone.', 'axellcore-atelierclub' ),
				'content'     => file_get_contents( $template_path ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'post_types'  => array( 'page' ),
			)
		);
	}

	/**
	 * Native `position: sticky` (Group block's "Position" style panel) only
	 * renders its CSS when the active theme.json declares
	 * `settings.position.sticky: true` — otherwise
	 * wp_render_position_support() (wp-includes/block-supports/position.php)
	 * silently drops the style at render time, even if the attribute is set.
	 * twentytwentyfive doesn't opt into this. Enabling it via
	 * `wp_theme_json_data_theme` (a normal, documented filter) lets the
	 * styled sections use a real native block attribute for a fixed-feeling
	 * header instead of a custom CSS class — without editing the
	 * theme's own theme.json file. Site-wide, not scoped to our template:
	 * enabling this setting has no visible effect on blocks that don't set
	 * position.sticky themselves, so it's safe to leave broadly enabled.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme JSON data object.
	 * @return \WP_Theme_JSON_Data
	 */
	public function enable_position_sticky( $theme_json ) {
		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array(
					'position' => array(
						'sticky' => true,
					),
				),
			)
		);
	}

	/**
	 * Work around a WordPress core bug: when a `get_block_templates()` query
	 * matches ONLY a plugin-registered template (no theme file, no saved
	 * `wp_template`/`wp_template_part` post), the result comes back keyed by
	 * the template's `plugin//slug` string — e.g.
	 * `['axellcore-atelierclub//atelier-club' => WP_Block_Template]` — instead
	 * of the sequential `[0 => WP_Block_Template]` every other code path in
	 * core assumes.
	 *
	 * Root cause (confirmed by reading wp-includes/block-template-utils.php):
	 * `WP_Block_Templates_Registry::get_by_query()` returns its matches keyed
	 * by template name (`$matching_templates[$template_name] = $template`),
	 * and `array_merge()` — used to fold those into the query's result array
	 * — preserves *string* keys (only renumbers integer ones). Core's own
	 * `wp_get_post_content_block_attributes()` (wp-includes/block-editor.php)
	 * then does `$current_template[0]->content` unconditionally, which
	 * emits "Undefined array key 0" / "Attempt to read property content on
	 * null" warnings, cascading into `parse_blocks(null)` deprecation
	 * notices — reproduced on this install when opening the block editor for
	 * a page assigned to our plugin-only template.
	 *
	 * `array_values()` here restores the sequential-keys invariant for every
	 * `get_block_templates()` caller, not just the one that crashes on it —
	 * a normal, publicly-documented WordPress filter, not a core patch.
	 *
	 * @param \WP_Block_Template[] $templates Query result.
	 * @return \WP_Block_Template[] Re-indexed result.
	 */
	public function reindex_block_templates( $templates ) {
		return is_array( $templates ) ? array_values( $templates ) : $templates;
	}
}
