<?php
/**
 * "Reveal on scroll" block support for core blocks.
 *
 * The editor adds a `revealMode` attribute (none | block | items) to a fixed
 * list of core blocks (src/reveal/index.tsx). On the front end, render_block
 * adds the Interactivity API directives to the block's first element, so the
 * saved content and the layout stay the same.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reveal-on-scroll support: editor control, front-end markup and assets.
 */
final class Reveal {

	/**
	 * Script module, store and handle.
	 */
	const HANDLE = 'axellcore-atelierclub-reveal';

	/**
	 * Modes that add markup. `none` (the default) leaves the block untouched.
	 *
	 * @var string[]
	 */
	const MODES = array( 'block', 'items' );

	/**
	 * Singleton instance.
	 *
	 * @var Reveal|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Reveal
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
		add_action( 'init', array( $this, 'register_module' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
		add_filter( 'render_block', array( $this, 'decorate' ), 10, 2 );
	}

	/**
	 * Register the view module. It is enqueued only when a block uses it.
	 */
	public function register_module() {
		wp_register_script_module(
			self::HANDLE,
			AXELLCORE_ATELIERCLUB_URL . 'build/reveal/view.js',
			array( array( 'id' => '@wordpress/interactivity' ) ),
			AXELLCORE_ATELIERCLUB_VERSION
		);
	}

	/**
	 * Front-end stylesheet. Small, and it does nothing until the view module
	 * sets `is-ready`, so it is enqueued on every front-end page.
	 */
	public function enqueue_style() {
		wp_enqueue_style(
			self::HANDLE,
			AXELLCORE_ATELIERCLUB_URL . 'build/reveal/style-frontend.css',
			array(),
			AXELLCORE_ATELIERCLUB_VERSION
		);
	}

	/**
	 * Editor script that adds the Inspector control.
	 */
	public function enqueue_editor() {
		$asset_file = AXELLCORE_ATELIERCLUB_PATH . 'build/reveal/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_require -- Local build file, version and deps.

		wp_enqueue_script(
			self::HANDLE . '-editor',
			AXELLCORE_ATELIERCLUB_URL . 'build/reveal/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Add the reveal directives to a block's first element.
	 *
	 * @param string $content Rendered block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public function decorate( $content, $block ) {
		$mode = isset( $block['attrs']['revealMode'] ) ? $block['attrs']['revealMode'] : 'none';
		if ( ! in_array( $mode, self::MODES, true ) || '' === trim( $content ) ) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );
		if ( ! $processor->next_tag() ) {
			return $content;
		}

		$processor->add_class( 'axell-reveal' );
		$processor->set_attribute( 'data-axell-reveal', $mode );
		$processor->set_attribute( 'data-wp-interactive', 'axell/reveal' );
		$processor->set_attribute( 'data-wp-init', 'callbacks.observe' );

		wp_enqueue_script_module( self::HANDLE );

		return $processor->get_updated_html();
	}
}
