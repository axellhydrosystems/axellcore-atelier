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
		foreach ( self::descendants() as $page ) {
			self::create_descendant( $page );
		}
	}

	/**
	 * Pages under PAGE_SLUG, from content/pages.json: each entry has the
	 * page path below /atelier (e.g. "pure/adesao"), its title and its page
	 * template. Content lives in content/pages/{path}.html. Parents come
	 * before their children (bin/export-content.sh writes them that way).
	 *
	 * @return array<int,array{path:string,title:string,template:string}>
	 */
	private static function descendants() {
		$file = AXELLCORE_ATELIERCLUB_PATH . 'content/pages.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}
		$pages = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $pages ) ) {
			return array();
		}
		$valid = array();
		foreach ( $pages as $page ) {
			if ( is_array( $page ) && ! empty( $page['path'] ) ) {
				$valid[] = array(
					'path'     => trim( (string) $page['path'], '/' ),
					'title'    => (string) ( $page['title'] ?? '' ),
					'template' => (string) ( $page['template'] ?? '' ),
				);
			}
		}
		return $valid;
	}

	/**
	 * Create one page under PAGE_SLUG if it doesn't already exist. Its parent
	 * (the path without the last segment) must exist already.
	 *
	 * @param array{path:string,title:string,template:string} $page Page entry.
	 */
	private static function create_descendant( array $page ) {
		$full = self::PAGE_SLUG . '/' . $page['path'];
		if ( get_page_by_path( $full, OBJECT, 'page' ) instanceof \WP_Post ) {
			return;
		}
		$parent = get_page_by_path( dirname( $full ), OBJECT, 'page' );
		if ( ! $parent instanceof \WP_Post ) {
			return;
		}

		$page_id = self::insert_trusted_content(
			array(
				'post_type'    => 'page',
				'post_title'   => '' !== $page['title'] ? $page['title'] : basename( $full ),
				'post_name'    => basename( $full ),
				'post_status'  => 'publish',
				'post_parent'  => $parent->ID,
				'post_content' => self::read_content_file( self::page_file( $page['path'] ), '' ),
			)
		);
		if ( ! is_wp_error( $page_id ) && $page_id && '' !== $page['template'] ) {
			update_post_meta( $page_id, '_wp_page_template', $page['template'] );
		}
	}

	/**
	 * Option holding the hash of the page content files last synced.
	 */
	const PAGES_SYNC_OPTION = 'axellcore_atelierclub_pages_hash';

	/**
	 * Hook the content sync. Runs on every request, but only writes when a
	 * content file changed (same approach as Template_Parts::maybe_sync).
	 */
	public static function register_hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_sync_pages' ), 25 );
	}

	/**
	 * Update the pages under /atelier that already exist when their content
	 * file changed. Pages are never created here (activate() does that), and
	 * the landing itself is never touched: its content is edited in the database.
	 */
	public static function maybe_sync_pages() {
		$contents = array();
		$parts    = array();
		foreach ( self::descendants() as $page ) {
			$contents[ $page['path'] ] = self::read_content_file( self::page_file( $page['path'] ), '' );
			$parts[]                   = $page['path'] . ':' . md5( $contents[ $page['path'] ] );
		}
		$hash = md5( implode( '|', $parts ) );

		if ( get_option( self::PAGES_SYNC_OPTION ) === $hash ) {
			return;
		}

		foreach ( $contents as $path => $content ) {
			self::sync_page( $path, $content );
		}

		update_option( self::PAGES_SYNC_OPTION, $hash, false );
	}

	/**
	 * Write one page's content when it differs from its file.
	 *
	 * @param string $path    Page path below PAGE_SLUG.
	 * @param string $content Block markup from content/pages/{path}.html.
	 */
	private static function sync_page( $path, $content ) {
		$page = get_page_by_path( self::PAGE_SLUG . '/' . $path, OBJECT, 'page' );
		if ( ! $page instanceof \WP_Post || '' === $content || $content === $page->post_content ) {
			return;
		}

		kses_remove_filters();
		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => wp_slash( $content ),
			)
		);
		kses_init_filters();
	}

	/**
	 * Content file of a page below PAGE_SLUG.
	 *
	 * @param string $path Page path (e.g. "pure/adesao").
	 * @return string
	 */
	private static function page_file( $path ) {
		return AXELLCORE_ATELIERCLUB_PATH . 'content/pages/' . $path . '.html';
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
				'post_content' => self::read_content_file( AXELLCORE_ATELIERCLUB_PATH . 'content/atelier-page.html', '' ),
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

}
