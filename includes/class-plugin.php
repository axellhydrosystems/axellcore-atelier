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
		Inline_Icon::register_hooks();
		Design_Tokens::instance()->register_hooks();
		Member::instance()->register_hooks();
		Locations::instance()->register_hooks();
		Members::instance()->register_hooks();
		Form_Block::instance()->register_hooks();
		Form_Directives::instance()->register_hooks();
		Form_Submission::instance()->register_hooks();
		Activator::register_hooks();
		Resellers::instance()->register_hooks();
		Reseller_Store::register_hooks();
		Resellers_Rest::instance()->register_hooks();
		Resellers_Admin::instance()->register_hooks();
		Seo::instance()->register_hooks();
		Rest::instance()->register_hooks();
		Options_Rest::instance()->register_hooks();
		Kses::instance()->register_hooks();
		Admin_Rest::instance()->register_hooks();
		Members_Export::instance()->register_hooks();
		Template_Parts::instance()->register_hooks();
		Classic_Template::instance()->register_hooks();
		Classic_Styles::instance()->register_hooks();
		Reveal::instance()->register_hooks();
		Weglot::instance()->register_hooks();
	}
}
