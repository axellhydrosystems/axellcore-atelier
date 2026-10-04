<?php
/**
 * Meta description for the Atelier pages, when no SEO plugin prints one.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints <meta name="description"> on /atelier and its child pages.
 */
final class Seo {

	/**
	 * Description of the landing page: the approved copy from the mockup.
	 */
	const LANDING_DESCRIPTION = 'O Atelier Axell Club é um clube por seleção. Para arquitetos e designers que transformam o banho em obra, o spa em poesia e o projeto em memória. Solicite sua adesão.';

	/**
	 * Singleton instance.
	 *
	 * @var Seo|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Seo
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
		add_action( 'wp_head', array( $this, 'print_description' ), 1 );
	}

	/**
	 * Print the description, unless an SEO plugin already does.
	 */
	public function print_description() {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
			return;
		}

		$page = get_queried_object();
		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type ) {
			return;
		}

		$landing = get_page_by_path( Activator::PAGE_SLUG, OBJECT, 'page' );
		if ( ! $landing instanceof \WP_Post ) {
			return;
		}

		if ( $page->ID === $landing->ID ) {
			$description = self::LANDING_DESCRIPTION;
		} elseif ( (int) $page->post_parent === (int) $landing->ID ) {
			$description = has_excerpt( $page ) ? get_the_excerpt( $page ) : get_bloginfo( 'description' );
		} else {
			return;
		}

		printf(
			'<meta name="description" content="%s" />' . "\n",
			esc_attr( wp_strip_all_tags( (string) $description ) )
		);
	}
}
