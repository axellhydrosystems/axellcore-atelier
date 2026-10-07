<?php
/**
 * Revendas dashboard (DataViews list and DataForm detail, src/admin/resellers/)
 * in place of the core list and edit screens, when JetEngine is not active.
 * It sits in the post type's own menu: "Todas as revendas" is the list, one
 * revenda is `&reseller=<id>`, a new one `&reseller=new`; the core screens
 * (edit.php, post-new.php, post.php) redirect there.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Revendas admin page.
 */
final class Resellers_Admin {

	/**
	 * Slug of the page (edit.php?post_type=revendas&page=resellers… is not
	 * used: the page is admin.php?page=resellers, under the post type menu).
	 */
	const ADMIN_PAGE = 'resellers';

	/**
	 * Hook suffix of the page, set when it is added.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Singleton instance.
	 *
	 * @var Resellers_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Resellers_Admin
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
	 * Register hooks (none with JetEngine, which owns the screens).
	 */
	public function register_hooks() {
		if ( Resellers::jet_engine_active() ) {
			return;
		}
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'load-edit.php', array( $this, 'redirect_core_screen' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_core_screen' ) );
		add_action( 'load-post.php', array( $this, 'redirect_core_screen' ) );
	}

	/**
	 * URL of the list, or of one revenda ('new' for the creation form).
	 *
	 * @param int|string $reseller Revenda ID, 'new', or 0 for the list.
	 * @return string
	 */
	public static function url( $reseller = 0 ) {
		$url = admin_url( 'admin.php?page=' . self::ADMIN_PAGE );
		return $reseller ? $url . '&reseller=' . rawurlencode( (string) $reseller ) : $url;
	}

	/**
	 * Replace the post type's list and "add" items with the dashboard.
	 */
	public function register_admin_page() {
		$parent = 'edit.php?post_type=' . Resellers::POST_TYPE;
		$type   = get_post_type_object( Resellers::POST_TYPE );
		if ( ! $type ) {
			return;
		}
		remove_submenu_page( $parent, $parent );
		remove_submenu_page( $parent, 'post-new.php?post_type=' . Resellers::POST_TYPE );

		$this->hook = (string) add_submenu_page(
			$parent,
			__( 'Resellers', 'axellcore-atelierclub' ),
			__( 'All resellers', 'axellcore-atelierclub' ),
			$type->cap->edit_posts,
			self::ADMIN_PAGE,
			array( $this, 'render_admin_page' ),
			0
		);
		add_submenu_page(
			$parent,
			__( 'Add reseller', 'axellcore-atelierclub' ),
			__( 'Add reseller', 'axellcore-atelierclub' ),
			$type->cap->create_posts,
			self::ADMIN_PAGE . '&reseller=new',
			'__return_null',
			1
		);
	}

	/**
	 * Send the core list and edit screens of revendas to the dashboard.
	 */
	public function redirect_core_screen() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
		global $pagenow;
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
		$target    = '';
		if ( 'post.php' === $pagenow ) {
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
			if ( $post_id && 'edit' === $action && Resellers::POST_TYPE === get_post_type( $post_id ) ) {
				$target = self::url( $post_id );
			}
		} elseif ( Resellers::POST_TYPE === $post_type ) {
			// The trash and bulk actions stay on the core list.
			$keep   = isset( $_GET['post_status'] ) || isset( $_GET['action'] ) || isset( $_GET['trashed'] ) || isset( $_GET['untrashed'] );
			$target = 'post-new.php' === $pagenow ? self::url( 'new' ) : ( $keep ? '' : self::url() );
		}
		// phpcs:enable
		if ( '' !== $target ) {
			wp_safe_redirect( $target );
			exit;
		}
	}

	/**
	 * Page shell; the app mounts after its heading.
	 */
	public function render_admin_page() {
		$reseller = $this->current_reseller();
		$title    = 'new' === $reseller ? __( 'Add reseller', 'axellcore-atelierclub' ) : __( 'Resellers', 'axellcore-atelierclub' );
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
		if ( ! $reseller ) {
			printf(
				' <a href="%s" class="page-title-action">%s</a>',
				esc_url( self::url( 'new' ) ),
				esc_html__( 'Add reseller', 'axellcore-atelierclub' )
			);
		}
		echo '<hr class="wp-header-end"></div>';
	}

	/**
	 * Whether the current screen is the dashboard.
	 *
	 * @return bool
	 */
	private function is_admin_page() {
		$screen = get_current_screen();
		return $screen && '' !== $this->hook && $this->hook === $screen->id;
	}

	/**
	 * Revenda being viewed: its ID, 'new', or 0 on the list.
	 *
	 * @return int|string
	 */
	private function current_reseller() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view selection.
		$value = isset( $_GET['reseller'] ) ? sanitize_key( wp_unslash( $_GET['reseller'] ) ) : '';
		return 'new' === $value ? 'new' : absint( $value );
	}

	/**
	 * Enqueue the admin app on the dashboard only.
	 */
	public function enqueue_admin() {
		if ( ! $this->is_admin_page() ) {
			return;
		}
		$asset_file = AXELLCORE_ATELIERCLUB_PATH . 'build/admin/resellers/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset  = require $asset_file;
		$handle = 'axellcore-atelierclub-admin-resellers';
		wp_add_inline_script(
			'wp-api-fetch',
			'window.aaResellers = ' . wp_json_encode( $this->admin_config() ) . ';',
			'before'
		);
		wp_enqueue_script(
			$handle,
			AXELLCORE_ATELIERCLUB_URL . 'build/admin/resellers/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			$handle,
			AXELLCORE_ATELIERCLUB_URL . 'build/admin/resellers/style-index.css',
			array( 'wp-components', Assets::admin_dataviews_style() ),
			$asset['version']
		);
	}

	/**
	 * Config the admin app reads from window.aaResellers.
	 *
	 * @return array
	 */
	private function admin_config() {
		$options = static function ( array $map ) {
			$list = array();
			foreach ( $map as $value => $label ) {
				$list[] = array(
					'value' => (string) $value,
					'label' => (string) $label,
				);
			}
			return $list;
		};
		$terms   = static function ( $taxonomy ) {
			$names = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
					'fields'     => 'id=>name',
				)
			);
			return is_wp_error( $names ) ? array() : $names;
		};
		$slugs   = static function ( $taxonomy ) {
			$found = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);
			$map   = array();
			foreach ( is_wp_error( $found ) ? array() : $found as $term ) {
				$map[ $term->slug ] = $term->name;
			}
			return $map;
		};

		$reseller = $this->current_reseller();
		return array(
			'listUrl'    => self::url(),
			'editUrl'    => self::url() . '&reseller=',
			'resellerId' => 'new' === $reseller ? 0 : $reseller,
			'isNew'      => 'new' === $reseller,
			'statuses'   => $options( Resellers_Rest::statuses() ),
			'stateTerms' => $options( $slugs( Resellers::TAX_STATE ) ),
			'cityTerms'  => $options( $slugs( Resellers::TAX_CITY ) ),
			'countries'  => array_values( array_unique( array_merge( array( Reseller_Store::BRAZIL ), array_values( $terms( Resellers::TAX_COUNTRY ) ) ) ) ),
			'states'     => array_values( Locations::instance()->states() ),
		);
	}

	/**
	 * Body class the app's CSS and mount key off.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( ! $this->is_admin_page() ) {
			return $classes;
		}
		return $classes . ' ' . ( $this->current_reseller() ? 'aa-resellers-edit' : 'aa-resellers-list' );
	}
}
