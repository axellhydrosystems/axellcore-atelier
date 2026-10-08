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
		add_filter( 'wpseo_metadesc', array( $this, 'yoast_description' ) );
	}

	/**
	 * Print the description, unless an SEO plugin already does.
	 */
	public function print_description() {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
			return;
		}

		$description = self::description_for( get_queried_object() );
		if ( null === $description ) {
			return;
		}

		printf(
			'<meta name="description" content="%s" />' . "\n",
			esc_attr( wp_strip_all_tags( (string) $description ) )
		);
	}

	/**
	 * With Yoast SEO: the Atelier description when the page has none of its
	 * own (Yoast then prints no meta description at all).
	 *
	 * @param string|mixed $description Yoast's description.
	 * @return string|mixed
	 */
	public function yoast_description( $description ) {
		if ( '' !== trim( (string) $description ) ) {
			return $description;
		}
		$ours = self::description_for( get_queried_object() );
		return null === $ours ? $description : wp_strip_all_tags( $ours );
	}

	/**
	 * Description of an Atelier page: the landing's copy, a section page's
	 * excerpt (else the landing's copy: the site tagline may be empty, which
	 * Lighthouse counts as missing), or null for any other object.
	 *
	 * @param mixed $page Queried object.
	 * @return string|null
	 */
	public static function description_for( $page ) {
		if ( ! $page instanceof \WP_Post || 'page' !== $page->post_type ) {
			return null;
		}
		$landing = Settings::page();
		if ( ! $landing instanceof \WP_Post ) {
			return null;
		}
		if ( $page->ID === $landing->ID ) {
			return self::LANDING_DESCRIPTION;
		}
		if ( (int) $page->post_parent === (int) $landing->ID ) {
			return has_excerpt( $page ) ? get_the_excerpt( $page ) : self::LANDING_DESCRIPTION;
		}
		return null;
	}
}
