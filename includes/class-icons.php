<?php
/**
 * Registers the plugin's small icon set (WP 7.1's Icons API —
 * wp_register_icon_collection()/wp_register_icon(), rendered via the core
 * `core/icon` block) for the two icons that appear as standalone content
 * next to text (the tier "mystery" lock-mark and the notice-pill lock/clock
 * icons) — so editors pick these from the block inserter's icon picker
 * instead of the markup being frozen as raw Custom HTML.
 *
 * The repeated CTA-button trailing arrow is deliberately NOT registered
 * here: it's decorative chrome bound to the button link's own hover state
 * (see assets/css/blocks-bridge.css's ::after rule) rather than editable
 * content, so a CSS pseudo-element is the right tool for that one.
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Icon collection + icon registration.
 */
final class Icons {

	const COLLECTION = 'axellcore';

	/**
	 * Singleton instance.
	 *
	 * @var Icons|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Icons
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
		add_action( 'init', array( $this, 'register_icons' ) );
	}

	/**
	 * Register the "axellcore" icon collection and its icons.
	 */
	public function register_icons() {
		if ( ! function_exists( 'wp_register_icon_collection' ) ) {
			return; // WP < 7.1 — no Icons API; the lock/clock stay as raw markup wherever seeded.
		}

		wp_register_icon_collection(
			self::COLLECTION,
			array(
				'label'       => __( 'Atelier Axell Club', 'axellcore-atelier' ),
				'description' => __( 'Icons used by the Atelier Axell Club landing page.', 'axellcore-atelier' ),
			)
		);

		// Solid fill/path icons, not stroke outlines: WordPress core's own
		// `.wp-block-icon svg { fill: currentColor; }` rule (wp-includes/
		// blocks/icon/style.css) unconditionally overrides any `fill="none"`
		// on the source SVG — a CSS property always wins over an SVG
		// presentation attribute — so a stroke-only icon renders as a solid
		// filled shape instead of an outline. Every icon in core's own
		// library is fill/path-based for the same reason; matching that
		// shape (single filled path, no stroke) is the correct fix, not an
		// override of core CSS.
		// The design's button arrow (design/source .btn .arrow: an 18x10 path
		// stroked at 1.3), as the outline of that stroke so it is fill-only;
		// renders pixel-identical to the stroked original.
		wp_register_icon(
			self::COLLECTION . '/arrow',
			array(
				'label'   => __( 'Arrow', 'axellcore-atelier' ),
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 10" width="18" height="10" fill="currentColor"><path d="M0 5.65 L16 5.65 L16 4.35 L0 4.35ZM16.459619 4.540381 L12.459619 0.540381 L11.540381 1.459619 L15.540381 5.459619ZM15.540381 4.540381 L11.540381 8.540381 L12.459619 9.459619 L16.459619 5.459619Z"/></svg>',
			)
		);

		// The same arrow at the large buttons' stroke (design/source .btn-lg
		// .arrow: stroke-width 1.4), outlined the same way.
		wp_register_icon(
			self::COLLECTION . '/arrow-large',
			array(
				'label'   => __( 'Arrow (large)', 'axellcore-atelier' ),
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 10" width="18" height="10" fill="currentColor"><path d="M0 5.7 L16 5.7 L16 4.3 L0 4.3ZM16.494975 4.505025 L12.494975 0.505025 L11.505025 1.494975 L15.505025 5.494975ZM15.505025 4.505025 L11.505025 8.505025 L12.494975 9.494975 L16.494975 5.494975Z"/></svg>',
			)
		);

		// The tier link's small arrow (design/source .tier-cta: a 16x8 path
		// stroked at 1.2), outlined the same way.
		wp_register_icon(
			self::COLLECTION . '/arrow-small',
			array(
				'label'   => __( 'Arrow (small)', 'axellcore-atelier' ),
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 8" width="16" height="8" fill="currentColor"><path d="M0 4.6 L14 4.6 L14 3.4 L0 3.4ZM14.424264 3.575736 L11.424264 0.575736 L10.575736 1.424264 L13.575736 4.424264ZM13.575736 3.575736 L10.575736 6.575736 L11.424264 7.424264 L14.424264 4.424264Z"/></svg>',
			)
		);

		// The design's outline lock (design/source .notice-pill and .lock-mark:
		// a rect and a shackle stroked at 1.4 on a 14 grid), as the outline
		// of that stroke so it is fill-only.
		wp_register_icon(
			self::COLLECTION . '/lock-outline',
			array(
				'label'   => __( 'Lock (outline)', 'axellcore-atelier' ),
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14" width="14" height="14" fill="currentColor"><path d="M3.5 5.3H10.5A1.2 1.2 0 0 1 11.7 6.5V11.5A1.2 1.2 0 0 1 10.5 12.7H3.5A1.2 1.2 0 0 1 2.3 11.5V6.5A1.2 1.2 0 0 1 3.5 5.3ZM3.7 6.7V11.3H10.3V6.7ZM3.8 6V4A3.2 3.2 0 0 1 10.2 4V6H8.8V4A1.8 1.8 0 0 0 5.2 4V6Z"/></svg>',
			)
		);

		wp_register_icon(
			self::COLLECTION . '/lock',
			array(
				'label'   => __( 'Lock', 'axellcore-atelier' ),
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14" width="14" height="14" fill="currentColor"><path d="M7 1.5a2.5 2.5 0 0 0-2.5 2.5v1.75h-.75A.75.75 0 0 0 3 6.5v5.25c0 .414.336.75.75.75h6.5a.75.75 0 0 0 .75-.75V6.5a.75.75 0 0 0-.75-.75H9.5V4A2.5 2.5 0 0 0 7 1.5Zm1.25 4.25h-2.5V4a1.25 1.25 0 1 1 2.5 0v1.75Z"/></svg>',
			)
		);

		// The design's outline clock (design/source .benefits-prizes
		// .notice-pill: a circle and a hand stroked at 1.4 on a 14 grid), as
		// the outline of that stroke (Inkscape stroke-to-path) so it is
		// fill-only.
		wp_register_icon(
			self::COLLECTION . '/clock-outline',
			array(
				'label'   => __( 'Clock (outline)', 'axellcore-atelier' ),
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14" width="14" height="14" fill="currentColor"><path d="M7 0.80078125C3.5841258 0.80078125 0.80078125 3.5841258 0.80078125 7C0.80078125 10.415874 3.5841258 13.199219 7 13.199219C10.415874 13.199219 13.199219 10.415874 13.199219 7C13.199219 3.5841258 10.415874 0.80078125 7 0.80078125ZM7 2.1992188C9.659258 2.1992188 11.800781 4.340742 11.800781 7C11.800781 9.659258 9.659258 11.800781 7 11.800781C4.340742 11.800781 2.1992188 9.659258 2.1992188 7C2.1992188 4.340742 4.340742 2.1992188 7 2.1992188ZM6.3007812 4L6.3007812 7.3496094L8.5800781 9.0605469L9.4199219 7.9394531L7.6992188 6.6503906L7.6992188 4Z"/></svg>',
			)
		);

		wp_register_icon(
			self::COLLECTION . '/clock',
			array(
				'label'   => __( 'Clock', 'axellcore-atelier' ),
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14" width="14" height="14" fill="currentColor"><path d="M7 1.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Zm0 1.5a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm-.75 1a.75.75 0 0 0-.75.75v2.5c0 .199.079.39.22.53l1.5 1.5a.75.75 0 0 0 1.06-1.06L7.25 6.69V4.75A.75.75 0 0 0 6.5 4h-.25Z"/></svg>',
			)
		);
	}
}
