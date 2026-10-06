<?php
/**
 * Loader: wires up the template, assets, and block registration classes.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal bootstrap/loader — no business logic itself, just wiring.
 */
final class Plugin {

	/**
	 * The name passed to register_block_template(), in `plugin//slug` form.
	 * This is a registry key, not what ends up in a page's `_wp_page_template`
	 * meta — WordPress stores the bare template *slug* there (see TEMPLATE_SLUG),
	 * since WP_Block_Templates_Registry::register() splits `plugin//slug` and
	 * only assigns `$slug` to the resulting WP_Block_Template's `slug` property,
	 * which is what `resolve_block_template()`/`is_page_template()` match against
	 * (confirmed by reading wp-includes/block-template.php's resolve_block_template()
	 * and class-wp-block-templates-registry.php's register() directly).
	 */
	const TEMPLATE_NAME = 'axellcore-atelierclub//atelier-club';

	/**
	 * The bare slug half of TEMPLATE_NAME — what actually ends up in a page's
	 * `_wp_page_template` post meta, and what `is_page_template()` must be
	 * compared against. Used by the asset manager's dequeue/enqueue gate.
	 */
	const TEMPLATE_SLUG = 'atelier-club';

	/**
	 * Experimental sibling template at /atelier-noclass: the same design,
	 * rebuilt with zero `aac-` CSS classes — every visual property expressed
	 * as native core-block style attributes (color/typography/spacing/
	 * border/position) instead. Exists to find the real limit of that
	 * approach against this specific design, not as a production page.
	 */
	const NOCLASS_TEMPLATE_NAME = 'axellcore-atelierclub//atelier-club-noclass';

	/**
	 * Bare slug half of NOCLASS_TEMPLATE_NAME — see TEMPLATE_SLUG's docblock
	 * for why this distinction matters.
	 */
	const NOCLASS_TEMPLATE_SLUG = 'atelier-club-noclass';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
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
	 * Register all hooks.
	 */
	public function boot() {
		Template_Loader::instance()->register_hooks();
		Assets::instance()->register_hooks();
		Blocks::instance()->register_hooks();
		Icons::instance()->register_hooks();
		Member::instance()->register_hooks();
		Locations::instance()->register_hooks();
		Members::instance()->register_hooks();
		Form_Block::instance()->register_hooks();
		Form_Submission::instance()->register_hooks();
		Activator::register_hooks();
		// City names per UF for the revendas plugin, which only creates a
		// city term for a city in this list.
		add_filter(
			'axellcore_revendas_city_names',
			static function ( $names, $uf ) {
				$cities = include AXELLCORE_ATELIERCLUB_PATH . 'includes/data/br-cities.php';
				return array_merge( (array) $names, array_values( $cities[ strtoupper( (string) $uf ) ] ?? array() ) );
			},
			10,
			2
		);
		Seo::instance()->register_hooks();
		Rest::instance()->register_hooks();
		Options_Rest::instance()->register_hooks();
		Kses::instance()->register_hooks();
		Admin_Rest::instance()->register_hooks();
		Template_Parts::instance()->register_hooks();
		Classic_Template::instance()->register_hooks();
		Reveal::instance()->register_hooks();
	}
}
