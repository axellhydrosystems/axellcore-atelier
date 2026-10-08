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
		self::import_media();
		self::import_navigation();

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

		/**
		 * Whether activation also creates the pages under /atelier from
		 * content/pages.json (the pure and per-section pages of the rebuild).
		 * Off by default: a live site gets only the landing. Their content
		 * stays in content/pages/ for a development site that turns this on.
		 *
		 * @param bool $create Default false.
		 */
		if ( apply_filters( 'axellcore_atelierclub_create_child_pages', false ) ) {
			foreach ( self::descendants() as $page ) {
				self::create_descendant( $page );
			}
		}

		// Revendas from content/revendas.csv, once, without JetEngine.
		Resellers_Import::maybe_run();
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
		add_filter( 'block_core_navigation_render_fallback', array( __CLASS__, 'skip_own_navigation_fallback' ) );
	}

	/**
	 * A navigation block without a menu (e.g. the theme header's) falls back
	 * to the most recently published wp_navigation post, which would be a menu
	 * this plugin created for its own pages. Use the core page list instead
	 * in that case.
	 *
	 * @param array[] $fallback_blocks Fallback blocks chosen by core.
	 * @return array[]
	 */
	public static function skip_own_navigation_fallback( $fallback_blocks ) {
		$navigation = \WP_Navigation_Fallback::get_fallback();
		if ( ! $navigation instanceof \WP_Post || ! get_post_meta( $navigation->ID, self::NAVIGATION_META, true ) ) {
			return $fallback_blocks;
		}
		return array(
			array(
				'blockName'    => 'core/page-list',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
		);
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
			$contents[ $page['path'] ] = self::read_raw_file( self::page_file( $page['path'] ) );
			$parts[]                   = $page['path'] . ':' . md5( $contents[ $page['path'] ] );
		}
		$navigation = array();
		foreach ( self::navigation_entries() as $entry ) {
			$navigation[ $entry['file'] ] = self::read_raw_file( self::navigation_file( $entry['file'] ) );
			$parts[]                      = 'navigation/' . $entry['file'] . ':' . md5( $navigation[ $entry['file'] ] );
		}
		$hash = md5( implode( '|', $parts ) );

		if ( get_option( self::PAGES_SYNC_OPTION ) === $hash ) {
			return;
		}

		foreach ( $contents as $path => $content ) {
			self::sync_page( $path, '' === $content ? '' : self::localize( $content ) );
		}

		foreach ( $navigation as $file => $content ) {
			$id = self::find_navigation( $file );
			if ( $id && '' !== $content ) {
				$content = self::localize( $content );
				if ( get_post_field( 'post_content', $id ) !== $content ) {
					self::write_trusted(
						function () use ( $id, $content ) {
							return wp_update_post(
								array(
									'ID'           => $id,
									'post_content' => wp_slash( $content ),
								)
							);
						}
					);
				}
			}
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

		self::write_trusted(
			function () use ( $page, $content ) {
				return wp_update_post(
					array(
						'ID'           => $page->ID,
						'post_content' => wp_slash( $content ),
					)
				);
			}
		);
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
	 * Calls wp_insert_post() through write_trusted(): no KSES and no block
	 * CSS stripping for the duration of the call.
	 *
	 * All content inserted here is our own bundled, fully-trusted markup
	 * (never user input) — it includes <select>/<input>/<form> tags that
	 * KSES strips from post_content by default for accounts (or WP-CLI/
	 * no-user contexts, e.g. plugin activation via `wp plugin activate`)
	 * without the unfiltered_html capability.
	 *
	 * wp_insert_post() unslashes its input, so the args are slashed first:
	 * otherwise the backslashes of the JSON escapes in block comments (e.g.
	 * `&` in a block's custom CSS) are lost and the attributes break.
	 *
	 * @param array $postarr wp_insert_post() args, unslashed.
	 * @return int|\WP_Error
	 */
	private static function insert_trusted_content( array $postarr ) {
		return self::write_trusted(
			function () use ( $postarr ) {
				return wp_insert_post( wp_slash( $postarr ), true );
			}
		);
	}

	/**
	 * Runs a write of our bundled block markup without the content filters a
	 * user without unfiltered_html / edit_css gets: KSES, and (WordPress 7.0+)
	 * the filter that strips the blocks' `style.css`. Activation from WP-CLI
	 * and the content syncs on a visitor's request run without such a user.
	 * Afterwards both are set up again for the current user, as core does.
	 *
	 * @param callable $write Performs the write; its result is returned.
	 * @return mixed
	 */
	public static function write_trusted( callable $write ) {
		kses_remove_filters();
		if ( function_exists( 'wp_custom_css_remove_filters' ) ) {
			wp_custom_css_remove_filters();
		}
		try {
			return $write();
		} finally {
			kses_init();
			if ( function_exists( 'wp_custom_css_kses_init' ) ) {
				wp_custom_css_kses_init();
			}
		}
	}

	/**
	 * Read a bundled content file, or fall back to $fallback if missing/empty.
	 *
	 * @param string $path     Absolute file path.
	 * @param string $fallback Fallback content.
	 * @return string
	 */
	private static function read_content_file( string $path, string $fallback ): string {
		$content = self::read_raw_file( $path );
		return '' !== $content ? self::localize( $content ) : $fallback;
	}

	/**
	 * A bundled content file as it is on disk, or '' if missing/empty.
	 *
	 * @param string $path Absolute file path.
	 * @return string
	 */
	private static function read_raw_file( string $path ): string {
		if ( file_exists( $path ) ) {
			$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $content && '' !== trim( $content ) ) {
				return $content;
			}
		}
		return '';
	}

	/**
	 * Post meta marking an attachment imported from content/media/ (value:
	 * the file name), so it is found again instead of imported twice.
	 */
	const MEDIA_META = '_axellcore_atelierclub_media';

	/**
	 * Map of exported attachment id => array{id:int,url:string,old_url:string}
	 * for this site, or null until built.
	 *
	 * @var array<int,array{id:int,url:string,old_url:string}>|null
	 */
	private static $media_map = null;

	/**
	 * Media the content uses, from content/media.json (written by
	 * bin/export-content.sh): file name, the attachment id and URL on the
	 * site that exported it, title and alt text.
	 *
	 * @return array<int,array{file:string,id:int,url:string,title:string,alt:string}>
	 */
	private static function media_entries() {
		$file = AXELLCORE_ATELIERCLUB_PATH . 'content/media.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}
		$entries = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $entries ) ) {
			return array();
		}
		$valid = array();
		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['file'] ) && ! empty( $entry['id'] ) ) {
				$valid[] = array(
					'file'  => basename( (string) $entry['file'] ),
					'id'    => (int) $entry['id'],
					'url'   => (string) ( $entry['url'] ?? '' ),
					'title' => (string) ( $entry['title'] ?? '' ),
					'alt'   => (string) ( $entry['alt'] ?? '' ),
				);
			}
		}
		return $valid;
	}

	/**
	 * Attachment previously imported for a media file, if any.
	 *
	 * @param string $file File name in content/media/.
	 * @return int Attachment id, or 0.
	 */
	private static function find_media( $file ) {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => self::MEDIA_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Import the media files the content uses into the media library, unless
	 * already there. The files are copied into uploads directly (not through
	 * the upload checks): they are our own bundled files, and a type such as
	 * SVG may not be allowed for uploads on the site.
	 */
	private static function import_media() {
		foreach ( self::media_entries() as $entry ) {
			$source = AXELLCORE_ATELIERCLUB_PATH . 'content/media/' . $entry['file'];
			if ( self::find_media( $entry['file'] ) || ! file_exists( $source ) ) {
				continue;
			}

			$type = wp_check_filetype( $entry['file'], wp_get_mime_types() + array( 'svg' => 'image/svg+xml' ) );
			if ( empty( $type['type'] ) ) {
				continue;
			}

			$uploads = wp_upload_dir();
			if ( ! empty( $uploads['error'] ) || ! wp_mkdir_p( $uploads['path'] ) ) {
				continue;
			}
			$name = wp_unique_filename( $uploads['path'], $entry['file'] );
			$path = trailingslashit( $uploads['path'] ) . $name;
			if ( ! copy( $source, $path ) ) {
				continue;
			}

			$id = wp_insert_attachment(
				array(
					'post_mime_type' => $type['type'],
					'post_title'     => '' !== $entry['title'] ? $entry['title'] : pathinfo( $entry['file'], PATHINFO_FILENAME ),
					'post_status'    => 'inherit',
				),
				$path,
				0,
				true
			);
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}

			require_once ABSPATH . 'wp-admin/includes/image.php';
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
			update_post_meta( $id, self::MEDIA_META, $entry['file'] );
			if ( '' !== $entry['alt'] ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $entry['alt'] );
			}
		}
		self::$media_map = null;
	}

	/**
	 * Point the content at this site's attachments: the exported URL becomes
	 * the attachment URL here, and the exported id becomes the local one in
	 * the image class (wp-image-{id}) and in the image block's "id".
	 *
	 * @param string $content Block markup from content/.
	 * @return string
	 */
	private static function localize_media( $content ) {
		if ( null === self::$media_map ) {
			self::$media_map = array();
			foreach ( self::media_entries() as $entry ) {
				$id = self::find_media( $entry['file'] );
				if ( $id ) {
					self::$media_map[ $entry['id'] ] = array(
						'id'      => $id,
						'url'     => (string) wp_get_attachment_url( $id ),
						'old_url' => $entry['url'],
					);
				}
			}
		}

		$map = self::$media_map;
		foreach ( $map as $media ) {
			if ( '' !== $media['old_url'] && '' !== $media['url'] ) {
				$content = str_replace( $media['old_url'], $media['url'], $content );
			}
		}

		$content = preg_replace_callback(
			'/\bwp-image-(\d+)\b/',
			function ( $m ) use ( $map ) {
				return isset( $map[ (int) $m[1] ] ) ? 'wp-image-' . $map[ (int) $m[1] ]['id'] : $m[0];
			},
			$content
		);

		// The attachment id in the block comment too: the block's save()
		// writes wp-image-{id} from it, so a stale id makes the block invalid
		// in the editor. Image and Cover keep it in `id`, Media & Text in
		// `mediaId`.
		$keys = array(
			'image'      => 'id',
			'cover'      => 'id',
			'media-text' => 'mediaId',
		);
		return (string) preg_replace_callback(
			'/<!-- wp:(image|cover|media-text) (\{.*?\}) (\/)?-->/',
			function ( $m ) use ( $map, $keys ) {
				$key   = $keys[ $m[1] ];
				$attrs = json_decode( $m[2], true );
				if ( ! is_array( $attrs ) || ! isset( $attrs[ $key ], $map[ (int) $attrs[ $key ] ] ) ) {
					return $m[0];
				}
				return (string) preg_replace( '/"' . $key . '":' . (int) $attrs[ $key ] . '(?=[,}])/', '"' . $key . '":' . $map[ (int) $attrs[ $key ] ]['id'], $m[0], 1 );
			},
			$content
		);
	}

	/**
	 * Content with this site's attachments and navigation menus.
	 *
	 * @param string $content Block markup from content/.
	 * @return string
	 */
	public static function localize( $content ) {
		return self::localize_navigation( self::localize_media( $content ) );
	}

	/**
	 * Post meta marking a wp_navigation post created from content/navigation/
	 * (value: the file name).
	 */
	const NAVIGATION_META = '_axellcore_atelierclub_navigation';

	/**
	 * Navigation menus the content uses, from content/navigation.json (written
	 * by bin/export-content.sh): file in content/navigation/, the menu's id on
	 * the site that exported it, and its title.
	 *
	 * @return array<int,array{file:string,id:int,title:string}>
	 */
	private static function navigation_entries() {
		$file = AXELLCORE_ATELIERCLUB_PATH . 'content/navigation.json';
		if ( ! file_exists( $file ) ) {
			return array();
		}
		$entries = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $entries ) ) {
			return array();
		}
		$valid = array();
		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['file'] ) && ! empty( $entry['id'] ) ) {
				$valid[] = array(
					'file'  => basename( (string) $entry['file'] ),
					'id'    => (int) $entry['id'],
					'title' => (string) ( $entry['title'] ?? '' ),
				);
			}
		}
		return $valid;
	}

	/**
	 * Path of a navigation menu's content file.
	 *
	 * @param string $file File name in content/navigation/.
	 * @return string
	 */
	private static function navigation_file( $file ) {
		return AXELLCORE_ATELIERCLUB_PATH . 'content/navigation/' . $file;
	}

	/**
	 * Navigation menu previously created for a content file, if any.
	 *
	 * @param string $file File name in content/navigation/.
	 * @return int Post id, or 0.
	 */
	private static function find_navigation( $file ) {
		$ids = get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => array( 'publish', 'draft' ),
				'meta_key'       => self::NAVIGATION_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Create the navigation menus the content uses, unless already there.
	 */
	private static function import_navigation() {
		foreach ( self::navigation_entries() as $entry ) {
			$content = self::read_raw_file( self::navigation_file( $entry['file'] ) );
			if ( self::find_navigation( $entry['file'] ) || '' === $content ) {
				continue;
			}
			$id = self::insert_trusted_content(
				array(
					'post_type'    => 'wp_navigation',
					'post_title'   => '' !== $entry['title'] ? $entry['title'] : pathinfo( $entry['file'], PATHINFO_FILENAME ),
					'post_status'  => 'publish',
					'post_content' => self::localize_media( $content ),
				)
			);
			if ( ! is_wp_error( $id ) && $id ) {
				update_post_meta( $id, self::NAVIGATION_META, $entry['file'] );
			}
		}
	}

	/**
	 * Point navigation blocks at this site's menus: the exported "ref"
	 * becomes the id of the menu created from the same file.
	 *
	 * @param string $content Block markup from content/.
	 * @return string
	 */
	private static function localize_navigation( $content ) {
		$map = array();
		foreach ( self::navigation_entries() as $entry ) {
			$id = self::find_navigation( $entry['file'] );
			if ( $id ) {
				$map[ $entry['id'] ] = $id;
			}
		}
		if ( ! $map ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/<!-- wp:navigation (\{.*?\}) (\/)?-->/',
			function ( $m ) use ( $map ) {
				$attrs = json_decode( $m[1], true );
				if ( ! is_array( $attrs ) || ! isset( $attrs['ref'], $map[ (int) $attrs['ref'] ] ) ) {
					return $m[0];
				}
				return (string) preg_replace( '/"ref":' . (int) $attrs['ref'] . '(?=[,}])/', '"ref":' . $map[ (int) $attrs['ref'] ], $m[0], 1 );
			},
			$content
		);
	}
}
