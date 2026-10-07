<?php
/**
 * Admin REST endpoints behind the Revendas dashboard (DataViews list and
 * DataForm detail, src/admin/resellers/), used when JetEngine is not active:
 *  - GET    /axellcore-atelierclub/v1/admin/resellers       — paginated, filterable list.
 *  - POST   /axellcore-atelierclub/v1/admin/resellers       — create.
 *  - GET    /axellcore-atelierclub/v1/admin/resellers/{id}  — one record.
 *  - POST   /axellcore-atelierclub/v1/admin/resellers/{id}  — partial update.
 *  - DELETE /axellcore-atelierclub/v1/admin/resellers/{id}  — move to the trash.
 *
 * Permissions are the post type's capabilities.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin revenda REST routes.
 */
final class Resellers_Rest {

	/**
	 * Largest page size the list endpoint will return.
	 */
	const PER_PAGE_MAX = 100;

	/**
	 * Request field => meta key.
	 *
	 * @var array<string,string>
	 */
	const FIELD_META = array(
		'address' => 'endereco',
		'phone_1' => 'telefone-1',
		'phone_2' => 'telefone-2',
		'website' => 'site',
		'email'   => 'e-mail',
	);

	/**
	 * Statuses the dashboard shows and sets.
	 *
	 * @return array<string,string> Status => label.
	 */
	public static function statuses() {
		return array(
			'publish' => __( 'Publicada', 'axellcore-atelierclub' ),
			'pending' => __( 'Pendente', 'axellcore-atelierclub' ),
			'draft'   => __( 'Rascunho', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * Singleton instance.
	 *
	 * @var Resellers_Rest|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Resellers_Rest
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
	 * Register the routes (not with JetEngine, which owns the screens).
	 */
	public function register_routes() {
		if ( Resellers::jet_engine_active() ) {
			return;
		}

		$text = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
		register_rest_route(
			Rest::NAMESPACE,
			'/admin/resellers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_resellers' ),
					'permission_callback' => array( $this, 'can_list' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => self::PER_PAGE_MAX,
						),
						'search'   => $text,
						'state'    => $text,
						'city'     => $text,
						'status'   => $text,
						'orderby'  => array(
							'type'    => 'string',
							'default' => 'title',
							'enum'    => array( 'date', 'title' ),
						),
						'order'    => array(
							'type'    => 'string',
							'default' => 'asc',
							'enum'    => array( 'asc', 'desc' ),
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_reseller' ),
					'permission_callback' => array( $this, 'can_create' ),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/admin/resellers/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_reseller' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_reseller' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'trash_reseller' ),
					'permission_callback' => array( $this, 'can_delete' ),
				),
			)
		);
	}

	/**
	 * Permission: may this user see the revendas.
	 *
	 * @return bool
	 */
	public function can_list() {
		$type = get_post_type_object( Resellers::POST_TYPE );
		return $type && current_user_can( $type->cap->edit_posts );
	}

	/**
	 * Permission: may this user create a revenda.
	 *
	 * @return bool
	 */
	public function can_create() {
		$type = get_post_type_object( Resellers::POST_TYPE );
		return $type && current_user_can( $type->cap->create_posts );
	}

	/**
	 * Permission: may this user edit this revenda.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit( \WP_REST_Request $request ) {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * Permission: may this user trash this revenda.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_delete( \WP_REST_Request $request ) {
		return current_user_can( 'delete_post', (int) $request['id'] );
	}

	/**
	 * GET /admin/resellers — one page of revendas.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_resellers( \WP_REST_Request $request ) {
		$status = (string) $request['status'];
		$args   = array(
			'post_type'      => Resellers::POST_TYPE,
			'post_status'    => isset( self::statuses()[ $status ] ) ? $status : array_keys( self::statuses() ),
			'posts_per_page' => (int) $request['per_page'],
			'paged'          => (int) $request['page'],
			's'              => (string) $request['search'],
			'orderby'        => 'date' === $request['orderby'] ? 'date' : 'title',
			'order'          => 'desc' === $request['order'] ? 'DESC' : 'ASC',
		);

		$tax = array();
		foreach ( array(
			'state' => Resellers::TAX_STATE,
			'city'  => Resellers::TAX_CITY,
		) as $param => $taxonomy ) {
			if ( '' !== $request[ $param ] ) {
				$tax[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => (string) $request[ $param ],
				);
			}
		}
		if ( $tax ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- admin-only list.
		}

		$query    = new \WP_Query( $args );
		$response = rest_ensure_response( array_map( array( $this, 'record' ), $query->posts ) );
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * GET /admin/resellers/{id}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_reseller( \WP_REST_Request $request ) {
		$post = $this->reseller( (int) $request['id'] );
		return is_wp_error( $post ) ? $post : rest_ensure_response( $this->record( $post ) );
	}

	/**
	 * POST /admin/resellers — create a revenda from the given fields.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_reseller( \WP_REST_Request $request ) {
		$params = $this->params( $request );
		$title  = sanitize_text_field( $this->param_string( $params, 'title' ) );
		if ( '' === $title ) {
			return new \WP_Error( 'aa_missing_field', __( 'O nome é obrigatório.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
		}
		$status  = $this->param_string( $params, 'status' );
		$post_id = wp_insert_post(
			array(
				'post_type'   => Resellers::POST_TYPE,
				'post_title'  => $title,
				'post_status' => isset( self::statuses()[ $status ] ) ? $status : 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$saved = $this->apply( (int) $post_id, $params );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$response = rest_ensure_response( $this->record( get_post( $post_id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * POST /admin/resellers/{id} — update any subset of the fields.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_reseller( \WP_REST_Request $request ) {
		$post = $this->reseller( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$params = $this->params( $request );

		$postarr = array( 'ID' => $post->ID );
		if ( isset( $params['title'] ) ) {
			$title = sanitize_text_field( $this->param_string( $params, 'title' ) );
			if ( '' === $title ) {
				return new \WP_Error( 'aa_missing_field', __( 'O nome é obrigatório.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
			}
			$postarr['post_title'] = $title;
		}
		if ( isset( $params['status'] ) ) {
			$status = $this->param_string( $params, 'status' );
			if ( ! isset( self::statuses()[ $status ] ) ) {
				return new \WP_Error( 'aa_invalid_status', __( 'Status inválido.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
			}
			$postarr['post_status'] = $status;
		}
		if ( count( $postarr ) > 1 ) {
			$updated = wp_update_post( $postarr, true );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		$saved = $this->apply( $post->ID, $params );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return rest_ensure_response( $this->record( get_post( $post->ID ) ) );
	}

	/**
	 * DELETE /admin/resellers/{id} — move to the trash.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function trash_reseller( \WP_REST_Request $request ) {
		$post = $this->reseller( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! wp_trash_post( $post->ID ) ) {
			return new \WP_Error( 'aa_trash_failed', __( 'Não foi possível mover para a lixeira.', 'axellcore-atelierclub' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'trashed' => true ) );
	}

	/**
	 * Save the meta and location fields present in $params.
	 *
	 * @param int   $post_id Revenda ID.
	 * @param array $params  Decoded JSON body.
	 * @return true|\WP_Error
	 */
	private function apply( int $post_id, array $params ) {
		foreach ( self::FIELD_META as $field => $key ) {
			if ( ! isset( $params[ $field ] ) ) {
				continue;
			}
			$value = trim( $this->param_string( $params, $field ) );
			if ( 'website' === $field && '' !== $value ) {
				$value = esc_url_raw( $value );
			} elseif ( 'email' === $field && '' !== $value ) {
				$value = sanitize_email( $value );
				if ( ! is_email( $value ) ) {
					return new \WP_Error( 'aa_invalid_email', __( 'E-mail inválido.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
				}
			} else {
				$value = sanitize_text_field( $value );
			}
			update_post_meta( $post_id, $key, $value );
		}

		if ( isset( $params['country'] ) || isset( $params['state'] ) || isset( $params['city'] ) ) {
			$current = $this->location( $post_id );
			Reseller_Store::assign_location(
				$post_id,
				Reseller_Store::normalize_location(
					isset( $params['country'] ) ? sanitize_text_field( $this->param_string( $params, 'country' ) ) : $current['country'],
					isset( $params['state'] ) ? sanitize_text_field( $this->param_string( $params, 'state' ) ) : $current['state'],
					isset( $params['city'] ) ? sanitize_text_field( $this->param_string( $params, 'city' ) ) : $current['city']
				)
			);
		}
		return true;
	}

	/**
	 * A revenda (any dashboard status), or a 404 WP_Error.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private function reseller( int $id ) {
		$post = get_post( $id );
		if ( ! $post || Resellers::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return new \WP_Error( 'aa_not_found', __( 'Revenda não encontrada.', 'axellcore-atelierclub' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * Country, state and city names of a revenda.
	 *
	 * @param int $post_id Revenda ID.
	 * @return array{country:string,state:string,city:string}
	 */
	private function location( int $post_id ) {
		return array(
			'country' => Reseller_Store::term_name( $post_id, Resellers::TAX_COUNTRY ),
			'state'   => Reseller_Store::term_name( $post_id, Resellers::TAX_STATE ),
			'city'    => Reseller_Store::term_name( $post_id, Resellers::TAX_CITY ),
		);
	}

	/**
	 * The record shape the list and the detail use.
	 *
	 * @param \WP_Post $post Revenda.
	 * @return array
	 */
	private function record( \WP_Post $post ) {
		$data = array(
			'id'     => $post->ID,
			'title'  => $post->post_title,
			'status' => $post->post_status,
			'date'   => $post->post_date,
		) + $this->location( $post->ID );
		foreach ( self::FIELD_META as $field => $key ) {
			$data[ $field ] = (string) get_post_meta( $post->ID, $key, true );
		}
		return $data;
	}

	/**
	 * The JSON body as an array.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function params( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		return is_array( $params ) ? $params : array();
	}

	/**
	 * A scalar param as a string, '' when absent or not scalar.
	 *
	 * @param array  $params Decoded JSON body.
	 * @param string $key    Param name.
	 * @return string
	 */
	private function param_string( array $params, string $key ) {
		return isset( $params[ $key ] ) && is_scalar( $params[ $key ] ) ? (string) $params[ $key ] : '';
	}
}
