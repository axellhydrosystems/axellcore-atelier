<?php
/**
 * Submission of any axell/form (or axell/form-atelier): reads the form's own
 * settings from the saved post (never from the request), keeps only the fields
 * the form really has, then runs its optional actions: store as a post of the
 * chosen post type, and send an email.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles form submissions from the REST route and from admin-post.
 */
final class Form_Submission {

	/**
	 * admin-post action of the no-JavaScript submission.
	 */
	const ADMIN_ACTION = 'axellcore_form_submit';

	/**
	 * Blocks that are forms (their settings drive a submission).
	 *
	 * @var string[]
	 */
	const FORM_BLOCKS = array( 'axell/form', 'axell/form-atelier' );

	/**
	 * Request fields that are not form data.
	 *
	 * @var string[]
	 */
	const RESERVED = array( 'action', 'post_id', 'form_id', Members::HONEYPOT_FIELD );

	/**
	 * Singleton instance.
	 *
	 * @var Form_Submission|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Form_Submission
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
		add_action( 'admin_post_nopriv_' . self::ADMIN_ACTION, array( $this, 'handle_form_post' ) );
		add_action( 'admin_post_' . self::ADMIN_ACTION, array( $this, 'handle_form_post' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'editor_settings' ) );
	}

	/**
	 * Targets the form can store into, for the block's settings panel:
	 * members (users) and the post types with an admin UI.
	 *
	 * @param array $settings Block editor settings.
	 * @return array
	 */
	public function editor_settings( $settings ) {
		$types = array(
			array(
				'value' => Members::STORE,
				'label' => __( 'Membro (usuário)', 'axellcore-atelierclub' ),
			),
		);
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ), true ) ) {
				continue;
			}
			$types[] = array(
				'value' => $type->name,
				'label' => $type->labels->singular_name,
			);
		}
		$settings['axellFormPostTypes'] = $types;
		return $settings;
	}

	/**
	 * Run one submission.
	 *
	 * @param array  $params All request fields.
	 * @param string $ip     Client address (rate limit).
	 * @return array|\WP_Error
	 */
	public function handle( array $params, $ip ) {
		if ( ! empty( $params[ Members::HONEYPOT_FIELD ] ) ) {
			// Pretend it worked, so the bot learns nothing.
			return array(
				'success' => true,
				'id'      => 0,
			);
		}

		$form = self::find_form( absint( $params['post_id'] ?? 0 ), sanitize_key( $params['form_id'] ?? '' ) );
		if ( null === $form ) {
			return new \WP_Error( 'aa_form_not_found', __( 'Form not found.', 'axellcore-atelierclub' ), array( 'status' => 400 ) );
		}

		$limited = Members::instance()->check_rate_limit( $ip );
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		$settings = self::settings( $form );
		$fields   = self::form_fields( $form, $params );
		$result   = array(
			'success' => true,
			'id'      => 0,
		);

		if ( '' !== $settings['storePostType'] ) {
			$stored = self::store( $settings, $fields );
			if ( is_wp_error( $stored ) ) {
				return $stored;
			}
			$result['id'] = (int) $stored;
		}

		if ( $settings['sendEmail'] ) {
			self::send_email( $settings, $fields );
		}

		return $result;
	}

	/**
	 * No-JavaScript submission (admin-post.php): back to the page, with the result.
	 */
	public function handle_form_post() {
		$params = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public form, same trust boundary as the REST endpoint.
		$result = $this->handle( is_array( $params ) ? $params : array(), Members::client_ip() );
		$status = is_wp_error( $result ) ? 'error' : 'success';

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = home_url( '/' );
		}

		wp_safe_redirect( add_query_arg( 'axell-form', $status, $back ) );
		exit;
	}

	/**
	 * The form block with this id in the post, or null.
	 *
	 * @param int    $post_id Post that has the form.
	 * @param string $form_id Value of the block's formId attribute.
	 * @return array|null Parsed block.
	 */
	public static function find_form( $post_id, $form_id ) {
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || '' === $form_id || 'publish' !== get_post_status( $post ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}
		return self::search( parse_blocks( $post->post_content ), $form_id );
	}

	/**
	 * Depth-first search for the form block.
	 *
	 * @param array  $blocks  Parsed blocks.
	 * @param string $form_id Form id.
	 * @return array|null
	 */
	private static function search( array $blocks, $form_id ) {
		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'], self::FORM_BLOCKS, true ) && ( $block['attrs']['formId'] ?? '' ) === $form_id ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::search( $block['innerBlocks'], $form_id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * The block's settings with the block type defaults applied (the atelier
	 * form stores into members by default without saving the attribute).
	 *
	 * @param array $block Parsed form block.
	 * @return array{storePostType:string,storeStatus:string,titleField:string,sendEmail:bool,emailTo:string,emailSubject:string,emailBody:string}
	 */
	public static function settings( array $block ) {
		$type  = \WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
		$attrs = $type ? $type->prepare_attributes_for_render( (array) $block['attrs'] ) : (array) $block['attrs'];

		// Content saved before the actions existed: "Membro (REST)" stored members.
		if ( empty( $attrs['storePostType'] ) && ! empty( $attrs['submitsToRest'] ) ) {
			$attrs['storePostType'] = Members::STORE;
		}
		// Members were the aa_member post type; now they are users.
		$target = (string) ( $attrs['storePostType'] ?? '' );
		if ( 'aa_member' === $target ) {
			$target = Members::STORE;
		}

		$status = (string) ( $attrs['storeStatus'] ?? 'pending' );
		return array(
			'storePostType' => Members::STORE === $target || post_type_exists( $target ) ? $target : '',
			'storeStatus'   => in_array( $status, array( 'pending', 'publish', 'draft', 'private' ), true ) ? $status : 'pending',
			'titleField'    => (string) ( $attrs['titleField'] ?? '' ),
			'sendEmail'     => ! empty( $attrs['sendEmail'] ),
			'emailTo'       => (string) ( $attrs['emailTo'] ?? '' ),
			'emailSubject'  => (string) ( $attrs['emailSubject'] ?? '' ),
			'emailBody'     => (string) ( $attrs['emailBody'] ?? '' ),
		);
	}

	/**
	 * Submitted values of the fields the form really has (by the name
	 * attributes in its rendered markup). Anything else in the request is dropped.
	 *
	 * @param array $block  Parsed form block.
	 * @param array $params Request fields.
	 * @return array<string,string>
	 */
	public static function form_fields( array $block, array $params ) {
		$names = array();
		$html  = new \WP_HTML_Tag_Processor( render_block( $block ) );
		while ( $html->next_tag() ) {
			if ( ! in_array( $html->get_tag(), array( 'INPUT', 'SELECT', 'TEXTAREA' ), true ) ) {
				continue;
			}
			$name = $html->get_attribute( 'name' );
			if ( is_string( $name ) && '' !== $name && ! in_array( $name, self::RESERVED, true ) ) {
				$names[ $name ] = true;
			}
		}

		$fields = array();
		foreach ( array_keys( $names ) as $name ) {
			if ( isset( $params[ $name ] ) && is_scalar( $params[ $name ] ) ) {
				$fields[ $name ] = (string) $params[ $name ];
			}
		}
		return $fields;
	}

	/**
	 * Store the submission. A post type can have its own handler (members do);
	 * the default creates a post with each field as meta.
	 *
	 * @param array                $settings Form settings.
	 * @param array<string,string> $fields   Form fields.
	 * @return int|\WP_Error Post ID.
	 */
	private static function store( array $settings, array $fields ) {
		/**
		 * Handler that stores a submission into a post type.
		 *
		 * @param callable|null $handler   Receives ( $fields, $settings ), returns a post ID or WP_Error.
		 * @param string        $post_type Target post type.
		 */
		$handler = apply_filters( 'axellcore_form_store_handler', null, $settings['storePostType'] );
		if ( is_callable( $handler ) ) {
			return call_user_func( $handler, $fields, $settings );
		}

		$title = '' !== $settings['titleField'] && ! empty( $fields[ $settings['titleField'] ] )
			? sanitize_text_field( $fields[ $settings['titleField'] ] )
			/* translators: %s: date and time of the submission. */
			: sprintf( __( 'Envio de formulário %s', 'axellcore-atelierclub' ), wp_date( 'Y-m-d H:i' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => $settings['storePostType'],
				'post_title'  => $title,
				'post_status' => $settings['storeStatus'],
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		foreach ( $fields as $name => $value ) {
			update_post_meta( $post_id, '_form_' . sanitize_key( $name ), sanitize_textarea_field( $value ) );
		}
		return (int) $post_id;
	}

	/**
	 * Send the notification email. Tags: {field} and {all_fields}. A failure is
	 * logged but does not fail the submission.
	 *
	 * @param array                $settings Form settings.
	 * @param array<string,string> $fields   Form fields.
	 */
	private static function send_email( array $settings, array $fields ) {
		$to = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $settings['emailTo'] ) ) ) );
		if ( ! $to ) {
			$to = array( get_option( 'admin_email' ) );
		}

		$all = array();
		foreach ( $fields as $name => $value ) {
			$all[] = $name . ': ' . sanitize_textarea_field( $value );
		}
		$replace = static function ( $text ) use ( $fields, $all ) {
			$text = str_replace( '{all_fields}', implode( "\n", $all ), $text );
			return preg_replace_callback(
				'/\{([a-z0-9_\-]+)\}/i',
				static function ( $m ) use ( $fields ) {
					return isset( $fields[ $m[1] ] ) ? sanitize_textarea_field( $fields[ $m[1] ] ) : '';
				},
				$text
			);
		};

		$subject = $replace( '' !== $settings['emailSubject'] ? $settings['emailSubject'] : __( 'Novo envio de formulário', 'axellcore-atelierclub' ) );
		$body    = $replace( '' !== $settings['emailBody'] ? $settings['emailBody'] : '{all_fields}' );

		$headers = array();
		if ( ! empty( $fields['email'] ) && is_email( $fields['email'] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $fields['email'] );
		}

		if ( ! wp_mail( $to, wp_strip_all_tags( $subject ), $body, $headers ) ) {
			error_log( 'axellcore form: email not sent for ' . $settings['storePostType'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
