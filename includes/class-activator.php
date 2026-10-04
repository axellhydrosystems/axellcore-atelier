<?php
/**
 * Runs on plugin activation: creates the `/atelier` page (assigned to
 * our FSE template) plus its "Header" and "Footer" FSE Template Parts, if
 * they don't already exist yet — so the plugin is self-provisioning (a
 * fresh install, including a WordPress Playground preview, lands on a
 * working page with zero manual setup) and the nav/footer chrome is edited
 * separately from the page content, the same way a real block theme
 * organizes them (Site Editor → Patterns → Template Parts → Header/Footer).
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation provisioning.
 */
final class Activator {

	/**
	 * The page slug this plugin owns.
	 */
	const PAGE_SLUG = 'atelier';

	/**
	 * Template part slugs — match the `slug` attribute on the
	 * `core/template-part` blocks in templates/atelier-club.html.
	 */
	const HEADER_SLUG = 'axellcore-header';
	const FOOTER_SLUG = 'axellcore-footer';

	/**
	 * Create the Atelier Club page and its two template parts if they
	 * aren't already there.
	 *
	 * Idempotent: safe to run on every activation (e.g. deactivate/reactivate)
	 * without creating duplicates.
	 */
	public static function activate() {
		self::create_template_part(
			self::HEADER_SLUG,
			__( 'Atelier — Header', 'axellcore-atelierclub' ),
			'header',
			AXELLCORE_ATELIERCLUB_PATH . 'content/header-part.html'
		);

		self::create_template_part(
			self::FOOTER_SLUG,
			__( 'Atelier — Footer', 'axellcore-atelierclub' ),
			'footer',
			AXELLCORE_ATELIERCLUB_PATH . 'content/footer-part.html'
		);

		self::create_page();
		foreach ( self::child_pages() as $slug => $title ) {
			self::create_child_page(
				self::PAGE_SLUG,
				$slug,
				$title,
				AXELLCORE_ATELIERCLUB_PATH . 'content/pages/' . $slug . '.html'
			);
		}
	}

	/**
	 * Child pages of PAGE_SLUG: slug => title. One unstyled page per section
	 * of the landing (same copy, anchors on the landing still point to #),
	 * plus the apply form. Content files live in content/pages/{slug}.html.
	 *
	 * @return array<string,string>
	 */
	private static function child_pages() {
		return array(
			'inicio'        => __( 'Início', 'axellcore-atelierclub' ),
			'convite'       => __( 'Convite', 'axellcore-atelierclub' ),
			'manifesto'     => __( 'Manifesto', 'axellcore-atelierclub' ),
			'a-placa'       => __( 'A Placa', 'axellcore-atelierclub' ),
			'protagonistas' => __( 'Protagonistas', 'axellcore-atelierclub' ),
			'proposta'      => __( 'Proposta', 'axellcore-atelierclub' ),
			'o-nome'        => __( 'O Nome', 'axellcore-atelierclub' ),
			'como-entrar'   => __( 'Como Entrar', 'axellcore-atelierclub' ),
			'niveis'        => __( 'Níveis', 'axellcore-atelierclub' ),
			'beneficios'    => __( 'Benefícios', 'axellcore-atelierclub' ),
			'editorial'     => __( 'Editorial', 'axellcore-atelierclub' ),
			'chamada'       => __( 'Chamada para adesão', 'axellcore-atelierclub' ),
			'adesao'        => __( 'Adesão', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * Create a child page under $parent_slug if it doesn't already exist.
	 *
	 * Uses the default page template on purpose: the content is plain block
	 * markup with no styling, and the page should show the theme's defaults.
	 *
	 * @param string $parent_slug   Slug of the parent page (must exist).
	 * @param string $slug          Slug of the child page.
	 * @param string $title         Title of the child page.
	 * @param string $content_file  Absolute path to the block-markup content file.
	 */
	private static function create_child_page( string $parent_slug, string $slug, string $title, string $content_file ) {
		$parent = get_page_by_path( $parent_slug, OBJECT, 'page' );
		if ( ! $parent instanceof \WP_Post ) {
			return;
		}

		// Full path, so a same-named page elsewhere doesn't count as existing.
		if ( get_page_by_path( $parent_slug . '/' . $slug, OBJECT, 'page' ) instanceof \WP_Post ) {
			return;
		}

		self::insert_trusted_content(
			array(
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_parent'  => $parent->ID,
				'post_content' => self::read_content_file( $content_file, '' ),
			)
		);
	}

	/**
	 * Create the /atelier page if it doesn't already exist.
	 */
	private static function create_page() {
		$existing = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
		if ( $existing instanceof \WP_Post ) {
			return;
		}

		$page_id = self::insert_trusted_content(
			array(
				'post_type'    => 'page',
				'post_title'   => __( 'Atelier Axell Club', 'axellcore-atelierclub' ),
				'post_name'    => self::PAGE_SLUG,
				'post_status'  => 'publish',
				'post_content' => self::read_content_file(
					AXELLCORE_ATELIERCLUB_PATH . 'content/atelier-page.html',
					self::placeholder_content()
				),
			)
		);

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return;
		}

		// Bare slug, not the `plugin//slug` registration name — see
		// Plugin::TEMPLATE_SLUG for why.
		update_post_meta( $page_id, '_wp_page_template', Plugin::TEMPLATE_SLUG );
	}

	/**
	 * Create a `wp_template_part` post (Header or Footer) if one with this
	 * slug doesn't already exist for the active theme.
	 *
	 * Deliberately a real database post (not a `register_block_template()`
	 * registry entry): `core/template-part` looks up header/footer parts via
	 * a direct `WP_Query` for `post_type => wp_template_part` — it never
	 * touches `get_block_templates()`/`WP_Block_Templates_Registry` at all,
	 * so this sidesteps the core associative-array-keys bug worked around in
	 * Template_Loader::reindex_block_templates() (that bug is specific to
	 * the *registry* merge path, not to real `wp_template_part` posts).
	 *
	 * @param string $slug         Template part slug (post_name).
	 * @param string $title        Human-readable title.
	 * @param string $area         'header' or 'footer' (wp_template_part_area taxonomy term).
	 * @param string $content_file Absolute path to the block-markup content file.
	 */
	private static function create_template_part( string $slug, string $title, string $area, string $content_file ) {
		$existing = get_posts(
			array(
				'post_type'      => 'wp_template_part',
				'name'           => $slug,
				'post_status'    => array( 'publish', 'auto-draft', 'draft' ),
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);

		if ( ! empty( $existing ) ) {
			return;
		}

		$post_id = self::insert_trusted_content(
			array(
				'post_type'    => 'wp_template_part',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_content' => self::read_content_file( $content_file, '' ),
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		wp_set_post_terms( $post_id, array( get_stylesheet() ), 'wp_theme' );
		wp_set_post_terms( $post_id, array( $area ), 'wp_template_part_area' );
	}

	/**
	 * Calls wp_insert_post(), with KSES bypassed for the duration of the call.
	 *
	 * All content inserted here is our own bundled, fully-trusted markup
	 * (never user input) — it includes <select>/<input>/<form> tags that
	 * KSES strips from post_content by default for accounts (or WP-CLI/
	 * no-user contexts, e.g. plugin activation via `wp plugin activate`)
	 * without the unfiltered_html capability.
	 *
	 * @param array $postarr wp_insert_post() args.
	 * @return int|\WP_Error
	 */
	private static function insert_trusted_content( array $postarr ) {
		kses_remove_filters();
		$result = wp_insert_post( $postarr, true );
		kses_init_filters();
		return $result;
	}

	/**
	 * Read a bundled content file, or fall back to $fallback if missing/empty.
	 *
	 * @param string $path     Absolute file path.
	 * @param string $fallback Fallback content.
	 * @return string
	 */
	private static function read_content_file( string $path, string $fallback ): string {
		if ( file_exists( $path ) ) {
			$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $content && '' !== trim( $content ) ) {
				return $content;
			}
		}
		return $fallback;
	}

	/**
	 * Minimal placeholder content shown until the full landing-page sections
	 * are authored in the block editor. Deliberately plain core blocks.
	 *
	 * @return string Serialized block markup.
	 */
	private static function placeholder_content(): string {
		return implode(
			"\n",
			array(
				'<!-- wp:heading {"level":1,"className":"aac-display"} -->',
				'<h1 class="wp-block-heading aac-display">' . esc_html__( 'Atelier Axell Club', 'axellcore-atelierclub' ) . '</h1>',
				'<!-- /wp:heading -->',
				'<!-- wp:paragraph -->',
				'<p>' . esc_html__( 'This page is provisioned by the axellcore-atelierclub plugin. Replace this placeholder with the full landing-page sections in the block editor.', 'axellcore-atelierclub' ) . '</p>',
				'<!-- /wp:paragraph -->',
			)
		);
	}
}
