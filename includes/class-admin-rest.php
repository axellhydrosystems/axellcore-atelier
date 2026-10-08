<?php
/**
 * Admin-only REST endpoints backing the Members dashboard (DataViews list
 * + DataForms detail, see src/admin/members/). Members are users with the
 * member_pending or member role; their fields are user meta named as the form fields:
 *  - GET  /axellcore-atelierclub/v1/admin/members       — paginated, filterable list.
 *  - GET  /axellcore-atelierclub/v1/admin/members/{id}  — full record.
 *  - POST /axellcore-atelierclub/v1/admin/members/{id}  — partial update.
 *
 * Listing requires list_users; reading and updating one member, edit_user
 * on it. The list and the single-record read return the full CPF/CNPJ.
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
	 * Primary focus choices of the adesão form (stored value => label), for
	 * the dashboard's filter and edit controls.
	 *
	 * @var array<string,string>
	 */
	const PRIMARY_FOCUS_OPTIONS = array(
		'high_end_residential_architecture'  => 'Arquitetura residencial de alto padrão',
		'interior_design'                    => 'Design de interiores',
		'corporate_hospitality_architecture' => 'Arquitetura corporativa / hospitalidade',
		'wellness_spa_hospitality'           => 'Wellness · Spa · Hotelaria',
		'other'                              => 'Outros',
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
					'page'          => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'      => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => self::PER_PAGE_MAX,
					),
					'search'        => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'state'         => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'primary_focus' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'orderby'       => array(
						'type'    => 'string',
						'default' => 'date',
						'enum'    => array( 'date', 'title', 'state' ),
					),
					'order'         => array(
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
		return current_user_can( 'list_users' );
	}

	/**
	 * Permission: may this user read/update one specific member.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit_member( \WP_REST_Request $request ) {
		return current_user_can( 'edit_user', (int) $request['id'] );
	}

	/**
	 * GET /admin/members — one page of member summaries.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_members( \WP_REST_Request $request ) {
		$args = array(
			'role__in'    => array_keys( Member::roles() ),
			'number'      => (int) $request['per_page'],
			'paged'       => (int) $request['page'],
			'meta_query'  => $this->list_meta_filters( $request ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only list, small dataset.
			'order'       => 'asc' === $request['order'] ? 'ASC' : 'DESC',
			'count_total' => true,
		);
		if ( '' !== $request['search'] ) {
			$args['search']         = '*' . $request['search'] . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		if ( 'title' === $request['orderby'] ) {
			$args['orderby'] = 'display_name';
		} elseif ( 'state' === $request['orderby'] ) {
			$args['meta_key'] = 'state'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only list, small dataset.
			$args['orderby']  = 'meta_value';
		} else {
			$args['orderby'] = 'registered';
		}

		$query    = new \WP_User_Query( $args );
		$total    = (int) $query->get_total();
		$response = rest_ensure_response( array_map( array( $this, 'summarize' ), $query->get_results() ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / max( 1, (int) $request['per_page'] ) ) );

		return $response;
	}

	/**
	 * GET /admin/members/{id} — one full record.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_member( \WP_REST_Request $request ) {
		$user = $this->member_user( (int) $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		return rest_ensure_response( $this->detail( $user ) );
	}

	/**
	 * POST /admin/members/{id} — update any subset of the record's fields.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_member( \WP_REST_Request $request ) {
		$user = $this->member_user( (int) $request['id'] );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$userdata = array( 'ID' => $user->ID );
		if ( isset( $params['fullname'] ) ) {
			$fullname = sanitize_text_field( $this->param_string( $params, 'fullname' ) );
			if ( '' === $fullname ) {
				return new \WP_Error( 'aa_missing_field', __( 'Name cannot be empty.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
			}
			$userdata['display_name'] = $fullname;
			$userdata['nickname']     = $fullname;
		}
		if ( isset( $params['email'] ) ) {
			$email = sanitize_email( $this->param_string( $params, 'email' ) );
			if ( '' === $email || ! is_email( $email ) ) {
				return new \WP_Error( 'aa_invalid_email', __( 'Invalid email address.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
			}
			$owner = email_exists( $email );
			if ( $owner && (int) $owner !== $user->ID ) {
				return new \WP_Error( 'aa_email_exists', __( 'This e-mail is already registered.', 'axellcore-atelierclub' ), array( 'status' => 409 ) );
			}
			$userdata['user_email'] = $email;
		}
		if ( isset( $params['url'] ) ) {
			$userdata['user_url'] = esc_url_raw( $this->param_string( $params, 'url' ) );
		}
		if ( count( $userdata ) > 1 ) {
			$updated = wp_update_user( $userdata );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		if ( isset( $params['br_revenue_id'] ) ) {
			$document = Members::validate_document_for(
				$user->ID,
				$this->param_string( $params, 'br_revenue_id' ),
				isset( $params['profile_type'] ) ? $this->param_string( $params, 'profile_type' ) : $this->meta( $user->ID, 'profile_type' )
			);
			if ( is_wp_error( $document ) ) {
				return $document;
			}
			$this->store_meta( $user->ID, 'br_revenue_id', $document );
		}

		if ( isset( $params['state'] ) || isset( $params['city'] ) ) {
			$location = Members::location(
				'' !== $this->meta( $user->ID, 'country' ) ? $this->meta( $user->ID, 'country' ) : 'BR',
				isset( $params['state'] ) ? $this->param_string( $params, 'state' ) : $this->meta( $user->ID, 'state' ),
				isset( $params['city'] ) ? $this->param_string( $params, 'city' ) : $this->meta( $user->ID, 'city' )
			);
			if ( is_wp_error( $location ) ) {
				return $location;
			}
			$this->store_meta( $user->ID, 'state', $location[0] );
			$this->store_meta( $user->ID, 'city', $location[1] );
		}

		foreach ( Members::TEXT_META_FIELDS as $field ) {
			if ( 'br_revenue_id' !== $field && isset( $params[ $field ] ) ) {
				$this->store_meta( $user->ID, $field, sanitize_text_field( $this->param_string( $params, $field ) ) );
			}
		}

		// Approval: the status is the member role.
		if ( isset( $params['status'] ) ) {
			$role = $this->param_string( $params, 'status' );
			if ( ! isset( Member::roles()[ $role ] ) ) {
				return new \WP_Error( 'aa_invalid_status', __( 'Invalid status.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
			}
			if ( ! in_array( $role, $user->roles, true ) ) {
				$user->set_role( $role );
			}
		}

		return rest_ensure_response( $this->detail( get_userdata( $user->ID ) ) );
	}

	/**
	 * Fetch a member user (either member role), or a 404 WP_Error.
	 *
	 * @param int $id User ID.
	 * @return \WP_User|\WP_Error
	 */
	private function member_user( int $id ) {
		$user = get_userdata( $id );
		if ( ! $user || ! array_intersect( array_keys( Member::roles() ), $user->roles ) ) {
			return new \WP_Error( 'aa_not_found', __( 'Member not found.', 'axellcore-atelierclub' ), array( 'status' => 404 ) );
		}
		return $user;
	}

	/**
	 * Meta_query clauses for the list's state/primary_focus filters.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function list_meta_filters( \WP_REST_Request $request ) {
		$clauses = array();

		if ( '' !== $request['state'] ) {
			$clauses[] = array(
				'key'   => 'state',
				'value' => strtoupper( $request['state'] ),
			);
		}
		if ( '' !== $request['primary_focus'] ) {
			$clauses[] = array(
				'key'   => 'primary_focus',
				'value' => $request['primary_focus'],
			);
		}

		return $clauses ? array_merge( array( 'relation' => 'AND' ), $clauses ) : array();
	}

	/**
	 * The member's status: its member role.
	 *
	 * @param \WP_User $user Member user.
	 * @return string
	 */
	private function status( \WP_User $user ) {
		return in_array( Member::ROLE, $user->roles, true ) ? Member::ROLE : Member::ROLE_PENDING;
	}

	/**
	 * Compact row shape for the list view.
	 *
	 * @param \WP_User $user Member user.
	 * @return array
	 */
	private function summarize( \WP_User $user ) {
		return array(
			'id'            => $user->ID,
			'fullname'      => $user->display_name,
			'company'       => $this->meta( $user->ID, 'company' ),
			'email'         => $user->user_email,
			'phone'         => $this->meta( $user->ID, 'phone' ),
			'primary_focus' => $this->meta( $user->ID, 'primary_focus' ),
			'state'         => $this->meta( $user->ID, 'state' ),
			'city'          => $this->meta( $user->ID, 'city' ),
			'br_revenue_id' => $this->meta( $user->ID, 'br_revenue_id' ),
			// user_registered is UTC; the list shows the site's time.
			'data'          => get_date_from_gmt( (string) $user->user_registered ),
			'status'        => $this->status( $user ),
		);
	}

	/**
	 * Full record, for the detail/edit view. Includes the unmasked CPF/CNPJ.
	 *
	 * @param \WP_User $user Member user.
	 * @return array
	 */
	private function detail( \WP_User $user ) {
		$data = array(
			'id'       => $user->ID,
			'fullname' => $user->display_name,
			'email'    => $user->user_email,
			'login'    => $user->user_login,
			'url'      => $user->user_url,
			'state'    => $this->meta( $user->ID, 'state' ),
			'city'     => $this->meta( $user->ID, 'city' ),
			// Read-only text: the site's date and time formats and timezone.
			'data'     => (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $user->user_registered . ' UTC' ) ),
			'status'   => $this->status( $user ),
		);

		foreach ( Members::TEXT_META_FIELDS as $field ) {
			$data[ $field ] = $this->meta( $user->ID, $field );
		}

		// Partner stores are shown by their text, not their ID.
		$resellers = array();
		foreach ( Members::RESELLER_FIELDS as $field ) {
			$data[ $field ] = $this->meta( $user->ID, $field . '_title' );

			$reseller_id = (int) $this->meta( $user->ID, $field );
			if ( '' === $data[ $field ] && ! $reseller_id ) {
				continue;
			}
			// A store linked to a revenda shows its status: "pending" needs curation.
			$status      = $reseller_id ? (string) get_post_status( $reseller_id ) : '';
			$resellers[] = array(
				'field'   => $field,
				'title'   => $data[ $field ],
				'id'      => $reseller_id,
				'status'  => $status,
				'pending' => 'pending' === $status,
				'url'     => $reseller_id ? (string) get_edit_post_link( $reseller_id, 'raw' ) : '',
			);
		}
		$data['resellers'] = $resellers;

		return $data;
	}

	/**
	 * One member field (user meta under its name), '' when unset.
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Field name.
	 * @return string
	 */
	private function meta( int $user_id, string $name ) {
		return (string) get_user_meta( $user_id, $name, true );
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
	 * Write or (for an empty value) delete one member field.
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Field name.
	 * @param string $value   Sanitized value.
	 */
	private function store_meta( int $user_id, string $name, string $value ) {
		if ( '' === $value ) {
			delete_user_meta( $user_id, $name );
			return;
		}
		update_user_meta( $user_id, $name, $value );
	}
}
