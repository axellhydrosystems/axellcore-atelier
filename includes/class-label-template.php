<?php
/**
 * Renders the label of a selectable post from a template such as
 * "[post_title] - [tax:cidade] [tax:estado]".
 *
 * Tokens:
 * - [post_title]        the post title;
 * - [tax:kind]          name of the attached term whose `kind` meta equals `kind`
 *                       (e.g. [tax:cidade] or [tax:estado]);
 * - [meta:key]          a post meta value (key without the _ prefix is not added).
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless renderer.
 */
final class Label_Template {

	/**
	 * Render one post's label.
	 *
	 * @param \WP_Post $post     Post to render.
	 * @param string   $template Template with [tokens].
	 * @return string Plain text, with empty pieces removed.
	 */
	public static function render( \WP_Post $post, $template ) {
		$label = preg_replace_callback(
			'/\[(post_title|tax:[a-z0-9_-]+|meta:[a-z0-9_]+)\]/i',
			static function ( $match ) use ( $post ) {
				$token = $match[1];
				if ( 'post_title' === $token ) {
					return $post->post_title;
				}
				if ( 0 === strpos( $token, 'tax:' ) ) {
					return self::term_name( $post, substr( $token, 4 ) );
				}
				return (string) get_post_meta( $post->ID, substr( $token, 5 ), true );
			},
			(string) $template
		);

		// Collapse spaces left by empty tokens and drop separators that end up dangling.
		$label = trim( preg_replace( '/\s+/', ' ', (string) $label ) );
		return trim( $label, " -\u{2013}\u{2014}" );
	}

	/**
	 * Name of the attached term whose `kind` meta equals $kind.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $kind Term kind, e.g. cidade.
	 * @return string Term name, or '' when none matches.
	 */
	private static function term_name( \WP_Post $post, $kind ) {
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( get_term_meta( $term->term_id, 'kind', true ) === $kind ) {
					return $term->name;
				}
			}
		}
		return '';
	}
}
