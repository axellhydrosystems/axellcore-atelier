<?php
/**
 * REST endpoints for the Atelier Axell application form:
 *  - GET  /axellcore-atelier/v1/cities?uf=SP  — cities for the state/
 *    city cascading select (assets/js/frontend.js), public/read-only.
 *  - POST /axellcore-atelier/v1/members        — the real form
 *    submission handler: creates an `aa_member` post, resolves/assigns
 *    its Country > State > City term, and stores every other field as
 *    post meta.
 *
 * @package Axellcore_Atelier
 */

namespace Axellcore_Atelier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST route registration + handlers.
 */
final class Rest {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'axellcore-atelier/v1';

	/**
	 * Singleton instance.
	 *
	 * @var Rest|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Rest
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
			self::NAMESPACE,
			'/cities',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_cities' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'uf' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/document',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'check_document' ),
				// Public: the application form checks its CPF/CNPJ field. It
				// answers only valid/exists, and is rate limited per address.
				'permission_callback' => '__return_true',
				'args'                => array(
					'value' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit_form' ),
				// Public: any visitor submits a form; the form's own settings are
				// read from the saved post, never from the request.
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/members',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_member' ),
				// Public by design: an unauthenticated application form,
				// same trust boundary as any public HTML form posting to a
				// server endpoint — there's no authenticated session/action
				// to protect with a nonce here.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /cities?uf=XX — cities for one state, for the cascading select.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_cities( \WP_REST_Request $request ) {
		$uf     = strtoupper( (string) $request->get_param( 'uf' ) );
		$cities = Locations::instance()->cities_for_state( $uf );

		$items = array();
		foreach ( $cities as $code => $name ) {
			$items[] = array(
				'value' => $code,
				'label' => $name,
			);
		}

		usort(
			$items,
			function ( $a, $b ) {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return rest_ensure_response( $items );
	}

	/**
	 * Checks of /document per address in DOCUMENT_WINDOW seconds.
	 */
	const DOCUMENT_LIMIT = 30;

	/**
	 * Window of the /document limit, in seconds.
	 */
	const DOCUMENT_WINDOW = 600;

	/**
	 * GET /document?value=…&type=… — whether a CPF/CNPJ is valid (check
	 * digits, and of the type when given: cpf/individual or
	 * cnpj/legal_entity) and, when valid, already registered.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function check_document( \WP_REST_Request $request ) {
		$key   = 'axell_document_' . md5( Members::client_ip() );
		$count = (int) get_transient( $key );
		if ( $count >= self::DOCUMENT_LIMIT ) {
			return new \WP_Error( 'aa_rate_limited', __( 'Too many attempts. Try again in a few minutes.', 'axellcore-atelier' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, self::DOCUMENT_WINDOW );

		$document = Document::normalize( (string) $request->get_param( 'value' ) );
		$valid    = Document::is_valid( $document, Document::type_of( (string) $request->get_param( 'type' ) ) );
		return rest_ensure_response(
			array(
				'valid'  => $valid,
				'exists' => $valid && Members::document_exists( $document ),
			)
		);
	}

	/**
	 * POST /submit — run a form's actions (store, email) for a submission.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_form( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$result = Form_Submission::instance()->handle( (array) $params, Members::client_ip() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/**
	 * POST /members — create an aa_member post from a form submission.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_member( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$result = Members::instance()->submit( (array) $params, Members::client_ip() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
