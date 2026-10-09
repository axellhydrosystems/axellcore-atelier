<?php
/**
 * Admin-only REST endpoints backing the Members dashboard (DataViews list
 * + DataForms detail, see src/admin/members/). Members are users with the
 * member_pending or member role; their fields are user meta named as the form fields:
 *  - GET  /axellcore-atelier/v1/admin/members       — paginated, filterable list.
 *  - GET  /axellcore-atelier/v1/admin/members/{id}  — full record.
 *  - POST /axellcore-atelier/v1/admin/members/{id}  — partial update.
 *
 * Listing requires list_users; reading and updating one member, edit_user
 * on it. The list and the single-record read return the full CPF/CNPJ.
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

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
	 * Primary focus choices (stored value => label): stored by their label,
	 * fixed in pt_BR, so the data reads the same whatever WordPress's
	 * language. For the dashboard's filter and edit controls.
	 *
	 * @var array<string,string>
	 */
	const PRIMARY_FOCUS_OPTIONS = array(
		'Arquitetura residencial de alto padrão'  => 'Arquitetura residencial de alto padrão',
		'Design de interiores'                    => 'Design de interiores',
		'Arquitetura corporativa / hospitalidade' => 'Arquitetura corporativa / hospitalidade',
		'Wellness · Spa · Hotelaria'              => 'Wellness · Spa · Hotelaria',
		'Outros'                                  => 'Outros',
	);

	/**
	 * The adesão form's option values (in its saved block) => the label
	 * stored for them.
	 *
	 * @var array<string,string>
	 */
	const PRIMARY_FOCUS_SLUGS = array(
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
					'status'        => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array_merge( array( '' ), array_keys( Member::roles() ) ),
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
			'role__in'    => '' !== $request['status'] ? array( (string) $request['status'] ) : array_keys( Member::roles() ),
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
			$args['meta_key'] = Members::meta_key( 'state' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only list, small dataset.
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
				return new \WP_Error( 'aa_missing_field', __( 'Name cannot be empty.', 'axellcore-atelier' ), array( 'status' => 400 ) );
			}
			$userdata['display_name'] = $fullname;
			$userdata['nickname']     = $fullname;
			$name                     = Members::name_meta( $fullname );
			$userdata['first_name']   = $name['first_name'];
			$userdata['last_name']    = $name['last_name'];
		}
		if ( isset( $params['email'] ) ) {
			$email = sanitize_email( $this->param_string( $params, 'email' ) );
			if ( '' === $email || ! is_email( $email ) ) {
				return new \WP_Error( 'aa_invalid_email', __( 'Invalid email address.', 'axellcore-atelier' ), array( 'status' => 400 ) );
			}
			$owner = email_exists( $email );
			if ( $owner && (int) $owner !== $user->ID ) {
				return new \WP_Error( 'aa_email_exists', __( 'This e-mail is already registered.', 'axellcore-atelier' ), array( 'status' => 409 ) );
			}
			$userdata['user_email'] = $email;
		}
		if ( isset( $params['url'] ) ) {
			$url = Format::url( $this->param_string( $params, 'url' ) );
			if ( null === $url ) {
				return new \WP_Error( 'aa_invalid_url', Members::invalid_url_message(), array( 'status' => 400 ) );
			}
			$userdata['user_url'] = $url;
		}
		if ( count( $userdata ) > 1 ) {
			$updated = wp_update_user( $userdata );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}
		if ( isset( $name ) ) {
			update_user_meta( $user->ID, 'billing_first_name', $name['billing_first_name'] );
			update_user_meta( $user->ID, 'billing_last_name', $name['billing_last_name'] );
		}

		if ( isset( $params['br_revenue_id'] ) ) {
			$document = Members::validate_document_for(
				$user->ID,
				$this->param_string( $params, 'br_revenue_id' ),
				'' // The type follows the CPF/CNPJ.
			);
			if ( is_wp_error( $document ) ) {
				return $document;
			}
			Members::put( $user->ID, 'br_revenue_id', $document );
		}

		if ( isset( $params['state'] ) || isset( $params['city'] ) ) {
			$location = Members::location(
				'BR',
				isset( $params['state'] ) ? $this->param_string( $params, 'state' ) : Members::get( $user->ID, 'state' ),
				isset( $params['city'] ) ? $this->param_string( $params, 'city' ) : Members::get( $user->ID, 'city' )
			);
			if ( is_wp_error( $location ) ) {
				return $location;
			}
			Members::put( $user->ID, 'state', $location[0] );
			Members::put( $user->ID, 'city', $location[1] );
		}

		foreach ( Members::TEXT_META_FIELDS as $field ) {
			if ( ! in_array( $field, array( 'br_revenue_id', 'profile_type', 'country' ), true ) && isset( $params[ $field ] ) ) {
				Members::put( $user->ID, $field, sanitize_text_field( $this->param_string( $params, $field ) ) );
			}
		}

		// Approval: the status is the member role.
		if ( isset( $params['status'] ) ) {
			$role = $this->param_string( $params, 'status' );
			if ( ! isset( Member::roles()[ $role ] ) ) {
				return new \WP_Error( 'aa_invalid_status', __( 'Invalid status.', 'axellcore-atelier' ), array( 'status' => 400 ) );
			}
			if ( Member::ROLE === $role ) {
				Member::approve( $user->ID );
			} elseif ( ! in_array( $role, $user->roles, true ) ) {
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
			return new \WP_Error( 'aa_not_found', __( 'Member not found.', 'axellcore-atelier' ), array( 'status' => 404 ) );
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
				'key'   => Members::meta_key( 'state' ),
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
			'company'       => Members::get( $user->ID, 'company' ),
			'email'         => $user->user_email,
			'phone'         => Members::get( $user->ID, 'phone' ),
			'primary_focus' => Members::get( $user->ID, 'primary_focus' ),
			'state'         => Members::get( $user->ID, 'state' ),
			'city'          => Members::get( $user->ID, 'city' ),
			'br_revenue_id' => Members::get( $user->ID, 'br_revenue_id' ),
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
			'state'    => Members::get( $user->ID, 'state' ),
			'city'     => Members::get( $user->ID, 'city' ),
			// Read-only text: the site's date and time formats and timezone.
			'data'     => (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $user->user_registered . ' UTC' ) ),
			'status'   => $this->status( $user ),
			// The consent given on the form (LGPD), read-only, as HTML (its links).
			'consent'  => nl2br( Format::text_links( esc_html( Members::consent_summary( $user->ID ) ) ) ),
		);

		foreach ( Members::TEXT_META_FIELDS as $field ) {
			$data[ $field ] = Members::get( $user->ID, $field );
		}

		// Partner stores: their revenda's text (as resellerN, the fields the
		// screen reads) and status ("pending" needs curation).
		$resellers = array();
		foreach ( Members::RESELLER_FIELDS as $field ) {
			$data[ $field ] = '';
		}
		foreach ( Members::reseller_ids( $user->ID ) as $index => $reseller_id ) {
			$title = Members::reseller_title( $reseller_id );
			if ( '' === $title || ! isset( Members::RESELLER_FIELDS[ $index ] ) ) {
				continue;
			}
			$field          = Members::RESELLER_FIELDS[ count( $resellers ) ];
			$data[ $field ] = $title;
			$status         = (string) get_post_status( $reseller_id );
			$resellers[]    = array(
				'field'   => $field,
				'title'   => $title,
				'id'      => $reseller_id,
				'status'  => $status,
				'pending' => 'pending' === $status,
				'url'     => (string) get_edit_post_link( $reseller_id, 'raw' ),
			);
		}
		$data['resellers'] = $resellers;

		return $data;
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
}
