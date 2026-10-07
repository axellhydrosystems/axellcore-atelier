<?php
/**
 * REST endpoint that feeds the autocomplete control of the form.
 *
 * GET /axellcore-atelierclub/v1/options?post_type=…&q=…&template=…
 * Returns [ { id, label }, … ] for published posts of an allowed post type.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the options route.
 */
final class Options_Rest {

	const LIMIT = 20;

	/**
	 * Singleton instance.
	 *
	 * @var Options_Rest|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Options_Rest
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Post types the autocomplete may read. Other types are refused.
	 *
	 * @return string[]
	 */
	public static function allowed_post_types() {
		return (array) apply_filters( 'axellcore_atelierclub_option_post_types', array( 'revendas' ) );
	}

	/**
	 * Register the route.
	 */
	public function register_routes() {
		register_rest_route(
			Rest::NAMESPACE,
			'/options',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_type' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ) {
							return in_array( $value, self::allowed_post_types(), true );
						},
					),
					'q'         => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'template'  => array(
						'type'              => 'string',
						'default'           => '[post_title]',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'callback'            => array( $this, 'get_options' ),
			)
		);
	}

	/**
	 * Search published posts and return their rendered labels.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_options( $request ) {
		$post_type = $request->get_param( 'post_type' );
		$query     = $request->get_param( 'q' );
		$template  = $request->get_param( 'template' );

		$ids = self::search_ids( $post_type, $query );

		$items = array();
		if ( $ids ) {
			$posts = get_posts(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'publish',
					'post__in'               => $ids,
					'orderby'                => 'post__in',
					'posts_per_page'         => self::LIMIT,
					'no_found_rows'          => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => true,
				)
			);
			foreach ( $posts as $post ) {
				$items[] = array(
					'id'    => (int) $post->ID,
					'label' => Label_Template::render( $post, $template ),
				);
			}
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Post IDs matching the query: by title first, then by an attached term name
	 * (so a city or state finds the posts that carry it). Capped at LIMIT.
	 *
	 * @param string $post_type Allowed post type.
	 * @param string $query     Search text; '' lists the first posts by title.
	 * @return int[]
	 */
	private static function search_ids( $post_type, $query ) {
		$query = trim( (string) $query );

		$by_title = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				's'              => $query,
				'fields'         => 'ids',
				'posts_per_page' => self::LIMIT,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$ids      = array_map( 'intval', $by_title );

		return array_slice( array_values( array_unique( $ids ) ), 0, self::LIMIT );
	}
}
