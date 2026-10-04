<?php
/**
 * Admin-only REST endpoints backing the aac_member dashboard (DataViews list
 * + DataForms detail, see src/admin/members/):
 *  - GET  /axellcore-atelierclub/v1/admin/members       — paginated, filterable list.
 *  - GET  /axellcore-atelierclub/v1/admin/members/{id}  — full record.
 *  - POST /axellcore-atelierclub/v1/admin/members/{id}  — partial update.
 *
 * Everything here requires edit_posts (and edit_post per record). The list
 * masks CPF/CNPJ; the full value is only returned by the single-record read.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin member REST routes.
 */
final class Admin_Rest {

	/**
	 * Largest page size the list endpoint will return.
	 */
	const PER_PAGE_MAX = 100;

	/**
	 * Atuação choices offered by the application form, for the dashboard's
	 * filter and edit controls.
	 *
	 * @var string[]
	 */
	const ATUACAO_OPTIONS = array(
		'Arquitetura residencial de alto padrão',
		'Design de interiores',
		'Arquitetura corporativa / hospitalidade',
		'Wellness · Spa · Hotelaria',
		'Outros',
	);

	/**
	 * Singleton instance.
	 *
	 * @var Admin_Rest|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Admin_Rest
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
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			Rest::NAMESPACE,
			'/admin/members',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_members' ),
				'permission_callback' => array( $this, 'can_manage_members' ),
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
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'uf'       => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'atuacao'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'orderby'  => array(
						'type'    => 'string',
						'default' => 'date',
						'enum'    => array( 'date', 'title', 'uf' ),
					),
					'order'    => array(
						'type'    => 'string',
						'default' => 'desc',
						'enum'    => array( 'asc', 'desc' ),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/admin/members/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_member' ),
					'permission_callback' => array( $this, 'can_edit_member' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_member' ),
					'permission_callback' => array( $this, 'can_edit_member' ),
				),
			)
		);
	}

	/**
	 * Permission: may this user see the members dashboard at all.
	 *
	 * @return bool
	 */
	public function can_manage_members() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission: may this user read/update one specific member record.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_member( \WP_REST_Request $request ) {
		return current_user_can( 'edit_post', (int) $request['id'] );
	}

	/**
	 * GET /admin/members — one page of member summaries.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_members( \WP_REST_Request $request ) {
		$args = array(
			'post_type'      => Member::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => (int) $request['per_page'],
			'paged'          => (int) $request['page'],
			's'              => $request['search'],
			'meta_query'     => $this->list_meta_filters( $request ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only list, small dataset.
			'order'          => 'asc' === $request['order'] ? 'ASC' : 'DESC',
		);

		if ( 'title' === $request['orderby'] ) {
			$args['orderby'] = 'title';
		} elseif ( 'uf' === $request['orderby'] ) {
			$args['meta_key'] = '_aac_uf'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only list, small dataset.
			$args['orderby']  = 'meta_value';
		} else {
			$args['orderby'] = 'date';
		}

		$query    = new \WP_Query( $args );
		$response = rest_ensure_response( array_map( array( $this, 'summarize' ), $query->posts ) );
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * GET /admin/members/{id} — one full record.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_member( \WP_REST_Request $request ) {
		$post = $this->member_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return rest_ensure_response( $this->detail( $post ) );
	}

	/**
	 * POST /admin/members/{id} — update any subset of the record's fields.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_member( \WP_REST_Request $request ) {
		$post = $this->member_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		if ( isset( $params['nome'] ) ) {
			$nome = $this->param_string( $params, 'nome' );
			if ( '' === $nome ) {
				return new \WP_Error( 'aac_missing_field', __( 'Name cannot be empty.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
			}
			$updated = wp_update_post(
				array(
					'ID'         => $post->ID,
					'post_title' => $nome,
				),
				true
			);
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		if ( isset( $params['email'] ) ) {
			$email = sanitize_email( $this->param_string( $params, 'email' ) );
			if ( '' === $email || ! is_email( $email ) ) {
				return new \WP_Error( 'aac_invalid_email', __( 'Invalid email address.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
			}
			update_post_meta( $post->ID, '_aac_email', $email );
		}

		if ( isset( $params['portfolio'] ) ) {
			$this->store_meta( $post->ID, '_aac_portfolio', esc_url_raw( $this->param_string( $params, 'portfolio' ) ) );
		}

		if ( isset( $params['uf'] ) || isset( $params['cidade'] ) ) {
			$uf   = strtoupper( sanitize_text_field( isset( $params['uf'] ) ? $this->param_string( $params, 'uf' ) : (string) get_post_meta( $post->ID, '_aac_uf', true ) ) );
			$code = isset( $params['cidade'] ) ? absint( $this->param_string( $params, 'cidade' ) ) : ( $this->city_code( $post->ID, $uf ) ?? 0 );

			$term = Locations::instance()->resolve_city_term( $uf, $code );
			if ( null === $term ) {
				return new \WP_Error( 'aac_invalid_location', __( 'Invalid state/city.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
			}

			wp_set_object_terms( $post->ID, array( $term ), Locations::TAXONOMY );
			update_post_meta( $post->ID, '_aac_uf', $uf );
		}

		foreach ( Rest::TEXT_META_FIELDS as $field ) {
			if ( isset( $params[ $field ] ) ) {
				$this->store_meta( $post->ID, '_aac_' . $field, sanitize_text_field( $this->param_string( $params, $field ) ) );
			}
		}

		return rest_ensure_response( $this->detail( get_post( $post->ID ) ) );
	}

	/**
	 * Fetch an aac_member post, or a 404 WP_Error.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private function member_post( int $id ) {
		$post = get_post( $id );
		if ( ! $post || Member::POST_TYPE !== $post->post_type ) {
			return new \WP_Error( 'aac_not_found', __( 'Member not found.', 'axellcore-atelierclub' ), array( 'status' => 404 ) );
		}
		return $post;
	}

	/**
	 * Meta_query clauses for the list's uf/atuacao filters.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function list_meta_filters( \WP_REST_Request $request ) {
		$clauses = array();

		if ( '' !== $request['uf'] ) {
			$clauses[] = array(
				'key'   => '_aac_uf',
				'value' => strtoupper( $request['uf'] ),
			);
		}
		if ( '' !== $request['atuacao'] ) {
			$clauses[] = array(
				'key'   => '_aac_atuacao',
				'value' => $request['atuacao'],
			);
		}

		return $clauses ? array_merge( array( 'relation' => 'AND' ), $clauses ) : array();
	}

	/**
	 * Compact row shape for the list view. CPF/CNPJ is masked.
	 *
	 * @param \WP_Post $post Member post.
	 * @return array
	 */
	private function summarize( \WP_Post $post ) {
		return array(
			'id'                  => $post->ID,
			'nome'                => $post->post_title,
			'escritorio'          => (string) get_post_meta( $post->ID, '_aac_escritorio', true ),
			'email'               => (string) get_post_meta( $post->ID, '_aac_email', true ),
			'telefone'            => (string) get_post_meta( $post->ID, '_aac_telefone', true ),
			'atuacao'             => (string) get_post_meta( $post->ID, '_aac_atuacao', true ),
			'uf'                  => (string) get_post_meta( $post->ID, '_aac_uf', true ),
			'cidade'              => $this->city_name( $post->ID ),
			'documento_mascarado' => $this->mask_document( (string) get_post_meta( $post->ID, '_aac_documento', true ) ),
			'data'                => $post->post_date,
			'status'              => $post->post_status,
		);
	}

	/**
	 * Full record, for the detail/edit view. Includes unmasked CPF/CNPJ.
	 *
	 * @param \WP_Post $post Member post.
	 * @return array
	 */
	private function detail( \WP_Post $post ) {
		$uf   = (string) get_post_meta( $post->ID, '_aac_uf', true );
		$data = array(
			'id'          => $post->ID,
			'nome'        => $post->post_title,
			'email'       => (string) get_post_meta( $post->ID, '_aac_email', true ),
			'portfolio'   => (string) get_post_meta( $post->ID, '_aac_portfolio', true ),
			'uf'          => $uf,
			'cidade'      => $this->city_code( $post->ID, $uf ),
			'cidade_nome' => $this->city_name( $post->ID ),
			'data'        => $post->post_date,
			'status'      => $post->post_status,
		);

		foreach ( Rest::TEXT_META_FIELDS as $field ) {
			$data[ $field ] = (string) get_post_meta( $post->ID, '_aac_' . $field, true );
		}

		return $data;
	}

	/**
	 * Name of the member's city term, or '' if none.
	 *
	 * @param int $post_id Member post ID.
	 * @return string
	 */
	private function city_name( int $post_id ) {
		$terms = wp_get_object_terms( $post_id, Locations::TAXONOMY );
		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : '';
	}

	/**
	 * IBGE code of the member's city, reverse-looked-up from its term name
	 * within the given state, or null if it can't be matched.
	 *
	 * @param int    $post_id Member post ID.
	 * @param string $uf      Two-letter state code.
	 * @return int|null
	 */
	private function city_code( int $post_id, string $uf ) {
		$name = $this->city_name( $post_id );
		if ( '' === $name || '' === $uf ) {
			return null;
		}

		foreach ( Locations::instance()->cities_for_state( $uf ) as $code => $city ) {
			if ( $city === $name ) {
				return (int) $code;
			}
		}

		return null;
	}

	/**
	 * Hide all but the last four characters of a CPF/CNPJ.
	 *
	 * @param string $document Raw document value.
	 * @return string
	 */
	private function mask_document( string $document ) {
		$length = strlen( $document );
		if ( $length <= 4 ) {
			return $document;
		}
		return str_repeat( '•', $length - 4 ) . substr( $document, -4 );
	}

	/**
	 * Read a scalar request param as a string, '' when absent or non-scalar.
	 *
	 * @param array  $params Decoded JSON body.
	 * @param string $key    Param name.
	 * @return string
	 */
	private function param_string( array $params, string $key ) {
		return isset( $params[ $key ] ) && is_scalar( $params[ $key ] ) ? (string) $params[ $key ] : '';
	}

	/**
	 * Write or (for an empty value) delete one meta key.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $value   Sanitized value.
	 */
	private function store_meta( int $post_id, string $key, string $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}
}
