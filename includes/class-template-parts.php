<?php
/**
 * Header and footer for the Atelier landing page, read from the plugin's
 * content files (content/header-part.html, content/footer-part.html).
 *
 * On block themes the files are synced into the theme's template parts
 * (axellcore-header / axellcore-footer) whenever their content changes.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template part content source and FSE sync.
 */
final class Template_Parts {

	/**
	 * Option holding the hash of the files last synced into the theme.
	 */
	const SYNC_OPTION = 'axellcore_atelierclub_parts_hash';

	/**
	 * Part slug => content file, relative to the plugin root.
	 *
	 * @var array<string,string>
	 */
	const PARTS = array(
		'axellcore-header' => 'content/header-part.html',
		'axellcore-footer' => 'content/footer-part.html',
	);

	/**
	 * Singleton instance.
	 *
	 * @var Template_Parts|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Template_Parts
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
		add_action( 'init', array( $this, 'maybe_sync' ), 20 );
		add_filter( 'rest_post_dispatch', array( $this, 'author_text' ), 10, 3 );
	}

	/**
	 * Author shown for the Atelier parts in the site editor. They are created
	 * without a user, so WordPress shows the site's name; the templates
	 * controller has no rest_prepare filter, hence the dispatched response.
	 *
	 * @param \WP_HTTP_Response|mixed $result  Response.
	 * @param \WP_REST_Server         $server  Server.
	 * @param \WP_REST_Request        $request Request.
	 * @return \WP_HTTP_Response|mixed
	 */
	public function author_text( $result, $server, $request ) {
		if ( ! $result instanceof \WP_HTTP_Response || 0 !== strpos( $request->get_route(), '/wp/v2/template-parts' ) ) {
			return $result;
		}
		$data   = $result->get_data();
		$single = is_array( $data ) && isset( $data['id'] );
		$items  = $single ? array( $data ) : ( is_array( $data ) ? $data : array() );
		foreach ( $items as $i => $item ) {
			$slug = is_array( $item ) && isset( $item['id'] ) ? substr( (string) strstr( (string) $item['id'], '//' ), 2 ) : '';
			if ( isset( self::PARTS[ $slug ] ) && array_key_exists( 'author_text', $item ) && empty( $item['author'] ) ) {
				$items[ $i ]['author_text'] = 'Atelier Axell';
			}
		}
		$result->set_data( $single ? $items[0] : $items );
		return $result;
	}

	/**
	 * Rendered HTML of one part, from its content file.
	 *
	 * @param string $slug Part slug (key of PARTS).
	 * @return string Block markup, or '' if the file is missing.
	 */
	public function raw( $slug ) {
		if ( ! isset( self::PARTS[ $slug ] ) ) {
			return '';
		}
		$path = AXELLCORE_ATELIERCLUB_PATH . self::PARTS[ $slug ];
		if ( ! is_readable( $path ) ) {
			return '';
		}
		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Part HTML with blocks rendered, for classic templates.
	 *
	 * @param string $slug Part slug.
	 * @return string
	 */
	public function render( $slug ) {
		// The part edited in the site's template parts editor, else the file.
		$part = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
		if ( $part && ! empty( $part->wp_id ) && '' !== trim( (string) $part->content ) ) {
			return do_blocks( $part->content );
		}
		return do_blocks( $this->content( $slug ) );
	}

	/**
	 * A part's content for this site: the file with the media ids and URLs
	 * and the navigation refs of this install (the file holds the ones of the
	 * site it was exported from, e.g. localhost).
	 *
	 * @param string $slug Part slug.
	 * @return string
	 */
	public function content( $slug ) {
		$raw = $this->raw( $slug );
		return '' === $raw ? '' : Activator::localize( $raw );
	}

	/**
	 * Sync the files into the theme's template parts when they changed.
	 */
	public function maybe_sync() {
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return;
		}

		$hash = $this->files_hash();
		if ( get_option( self::SYNC_OPTION ) === $hash ) {
			return;
		}

		foreach ( array_keys( self::PARTS ) as $slug ) {
			$this->sync_part( $slug );
		}

		update_option( self::SYNC_OPTION, $hash, false );
	}

	/**
	 * Write one file's content into the matching theme template part.
	 *
	 * @param string $slug Part slug.
	 */
	private function sync_part( $slug ) {
		$template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
		if ( ! $template || empty( $template->wp_id ) ) {
			return;
		}

		$content = $this->content( $slug );
		if ( '' === $content || $content === $template->content ) {
			return;
		}

		// Trusted bundled markup (block CSS, inline icons): no content
		// filters, as the page sync (the request may have no user with
		// unfiltered_html or edit_css).
		Activator::write_trusted(
			function () use ( $template, $content ) {
				return wp_update_post(
					array(
						'ID'           => (int) $template->wp_id,
						'post_content' => wp_slash( $content ),
					)
				);
			}
		);
	}

	/**
	 * Hash of both content files, so a change to either triggers a sync.
	 *
	 * @return string
	 */
	private function files_hash() {
		$parts = array();
		foreach ( self::PARTS as $slug => $file ) {
			$parts[] = $slug . ':' . md5( $this->raw( $slug ) );
		}
		return md5( implode( '|', $parts ) );
	}
}
