<?php
/**
 * Registers the plugin's custom blocks — the application form
 * (axell/form, axell/fieldset, axell/form-label, axell/form-control,
 * axell/form-submission-notification, plus axellcore/form-input kept
 * registered-but-hidden for backward compatibility — see this plugin's
 * CLAUDE.md) and axellcore/chapters + axellcore/chapter (the page's
 * auto-numbered "Capítulo NN" sections) — and a handful of core Button
 * style variations used for the repeated CTA/chip affordances, keeping
 * everything else as plain core blocks per the "minimize custom blocks"
 * direction.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block + block-style registration.
 */
final class Blocks {

	/**
	 * Singleton instance.
	 *
	 * @var Blocks|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Blocks
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
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register the two custom blocks from their block.json metadata.
	 *
	 * Every other section uses plain core blocks with the original (now
	 * `aac-`-prefixed) classes applied via "Additional CSS class(es)" —
	 * including core/button, which gets `aac-btn aac-btn-primary`/`aac-btn-ghost`
	 * directly, reusing assets/css/sections.css's already-ported `.aac-btn*`
	 * rules verbatim instead of duplicating them under new block-style-variation
	 * selectors.
	 */
	public function register_blocks() {
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-input' ); // Deprecated, see its own header comment.
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-group' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-select' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-check' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-text' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-label' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control-reseller' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control-br-revenue-id' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control-br-revenue-id-person' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control-br-revenue-id-legal' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-address' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control-country' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control-state' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control-city' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-control-postal' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-fieldset' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/form/form-submission-notification' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/sticky-header' );
		register_block_type_from_metadata( AXELLCORE_ATELIERCLUB_PATH . 'build/chapters/chapters' );
		register_block_type_from_metadata(
			AXELLCORE_ATELIERCLUB_PATH . 'build/chapters/chapter',
			array( 'render_callback' => array( $this, 'render_chapter' ) )
		);
	}

	/**
	 * Injects a `data-chapter="…"` attribute (the translated word "Chapter"/
	 * "Capítulo") into the already-rendered static markup for one
	 * axellcore/chapter block.
	 *
	 * Deliberately NOT baked into stored post_content: the block itself
	 * stays a static block (its JS save() renders the InnerBlocks content
	 * and the numeral-less tag markup verbatim, so the editor's invalid-
	 * block recompute check still passes) — this render_callback only
	 * post-processes that already-correct HTML to add one translated
	 * attribute, resolved fresh on every request against the site's current
	 * locale via __(), same as any other gettext string. sections.css then
	 * reads it back with `content: attr(data-chapter) …` so the visible
	 * chapter number ("Capítulo 02 · Manifesto") is entirely generated
	 * content — never frozen text — in both language and number.
	 *
	 * @param array  $attributes Block attributes (unused — the word doesn't
	 *                           depend on them).
	 * @param string $content    The block's already-rendered save() output.
	 * @return string
	 */
	public function render_chapter( $attributes, $content ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		if ( $processor->next_tag( array( 'class_name' => 'aac-chapter-tag' ) ) ) {
			$processor->set_attribute( 'data-chapter', __( 'Chapter', 'axellcore-atelierclub' ) );
			return $processor->get_updated_html();
		}
		return $content;
	}
}
