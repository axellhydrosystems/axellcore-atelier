<?php
/**
 * Renders the label of a selectable post from a template such as
 * "[post_title] - [tax:estados:uf] [tax:cidades]".
 *
 * Tokens:
 * - [post_title]          the post title;
 * - [tax:taxonomy]        name of the post's first term in the taxonomy (e.g. [tax:cidades]);
 * - [tax:taxonomy:uf]     slug of that term in upper case (e.g. [tax:estados:uf] => SC);
 * - [meta:key]            a post meta value.
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
			'/\[(post_title|tax:[a-z0-9_-]+(?::[a-z]+)*|meta:[a-z0-9_-]+)\]/i',
			static function ( $found ) use ( $post ) {
				$token = $found[1];
				if ( 'post_title' === $token ) {
					return $post->post_title;
				}
				if ( 0 === strpos( $token, 'tax:' ) ) {
					$parts = explode( ':', substr( $token, 4 ) );
					return self::term_label( $post, array_shift( $parts ), $parts );
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
	 * Term label of the post's first term in a taxonomy.
	 *
	 * Taxonomy: the slug (estados) or its short name (estado, cidade, pais).
	 * Modifiers: slug (the term slug instead of its name) and uppercase (or uf,
	 * the same as slug:uppercase).
	 *
	 * @param \WP_Post $post      Post.
	 * @param string    $taxonomy  Taxonomy or its short name.
	 * @param string[]  $modifiers Modifiers after the taxonomy.
	 * @return string Term label, or '' when the post has no term there.
	 */
	private static function term_label( \WP_Post $post, $taxonomy, array $modifiers ) {
		$aliases  = array(
			'estado' => 'estados',
			'cidade' => 'cidades',
			'pais'   => 'paises',
		);
		$taxonomy = $aliases[ $taxonomy ] ?? $taxonomy;

		$terms = get_the_terms( $post->ID, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) {
			return '';
		}

		// uf is the short form of slug:uppercase (the state code).
		$slug  = in_array( 'slug', $modifiers, true ) || in_array( 'uf', $modifiers, true );
		$label = $slug ? $terms[0]->slug : $terms[0]->name;
		if ( in_array( 'uppercase', $modifiers, true ) || in_array( 'uf', $modifiers, true ) ) {
			$label = strtoupper( $label );
		}
		return $label;
	}
}
