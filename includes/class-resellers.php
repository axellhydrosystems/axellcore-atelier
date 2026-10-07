<?php
/**
 * Revendas: the post type, taxonomies and meta of production's JetEngine
 * setup (`~/Studio/axell`: post type `revendas`, flat terms in `paises`,
 * `estados`, `cidades`, the "Assistencia_Revendas" meta box), registered with
 * the same signature when JetEngine is not active, so content moves between
 * the two without changes. With JetEngine active nothing is registered here.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the revendas post type, its location taxonomies and meta fields.
 */
final class Resellers {

	const POST_TYPE = 'revendas';

	const TAX_COUNTRY = 'paises';
	const TAX_STATE   = 'estados';
	const TAX_CITY    = 'cidades';

	/**
	 * Meta keys and labels (JetEngine meta box "Assistencia_Revendas").
	 *
	 * @var array<string,string>
	 */
	const META_FIELDS = array(
		'endereco'   => 'Endereço',
		'telefone-1' => 'Telefone 1',
		'telefone-2' => 'Telefone 2',
		'site'       => 'Site',
		'e-mail'     => 'E-mail',
	);

	/**
	 * Singleton instance.
	 *
	 * @var Resellers|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Resellers
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
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Whether JetEngine is active (it then owns the post type).
	 *
	 * @return bool
	 */
	public static function jet_engine_active() {
		return class_exists( 'Jet_Engine' );
	}

	/**
	 * Register the post type, the taxonomies and the meta, unless JetEngine
	 * does (or they are already registered).
	 */
	public static function register() {
		if ( self::jet_engine_active() || post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Resellers', 'axellcore-atelierclub' ),
					'singular_name' => __( 'Reseller', 'axellcore-atelierclub' ),
					'all_items'     => __( 'All resellers', 'axellcore-atelierclub' ),
					'add_new_item'  => __( 'Add reseller', 'axellcore-atelierclub' ),
					'edit_item'     => __( 'Edit reseller', 'axellcore-atelierclub' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_rest'        => true,
				'query_var'           => true,
				'rewrite'             => array(
					'slug'       => 'revendas',
					'with_front' => true,
				),
				'map_meta_cap'        => true,
				'has_archive'         => true,
				'hierarchical'        => false,
				'exclude_from_search' => false,
				'capability_type'     => 'post',
				'menu_icon'           => 'dashicons-admin-home',
				'supports'            => array( 'title' ),
			)
		);

		$taxonomies = array(
			self::TAX_STATE   => array(
				'labels'    => array( 'name' => __( 'States', 'axellcore-atelierclub' ) ),
				'slug'      => 'estado',
				'query_var' => false,
			),
			self::TAX_CITY    => array(
				'labels'    => array(
					'name'          => __( 'Cities', 'axellcore-atelierclub' ),
					'singular_name' => __( 'City', 'axellcore-atelierclub' ),
					'all_items'     => __( 'All cities', 'axellcore-atelierclub' ),
				),
				'slug'      => 'cidade',
				'query_var' => self::TAX_CITY,
			),
			self::TAX_COUNTRY => array(
				'labels'    => array(
					'name'          => __( 'Countries', 'axellcore-atelierclub' ),
					'singular_name' => __( 'Country', 'axellcore-atelierclub' ),
					'all_items'     => __( 'All countries', 'axellcore-atelierclub' ),
				),
				'slug'      => 'paises',
				'query_var' => self::TAX_COUNTRY,
			),
		);
		foreach ( $taxonomies as $taxonomy => $args ) {
			register_taxonomy(
				$taxonomy,
				self::POST_TYPE,
				array(
					'labels'             => $args['labels'],
					'public'             => true,
					'publicly_queryable' => true,
					'show_ui'            => true,
					'show_in_menu'       => true,
					'show_in_nav_menus'  => true,
					'show_in_rest'       => true,
					'show_admin_column'  => true,
					'query_var'          => $args['query_var'],
					'hierarchical'       => true,
					'rewrite'            => array(
						'slug'         => $args['slug'],
						'with_front'   => false,
						'hierarchical' => false,
					),
				)
			);
		}

		foreach ( array_keys( self::META_FIELDS ) as $key ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
}
