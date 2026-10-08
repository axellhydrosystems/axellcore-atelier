<?php
/**
 * Member fields on the user edit screens (user-edit.php, profile.php), in
 * the mold of WooCommerce's customer fields (WC_Admin_Profile): fieldsets of
 * meta fields, filterable, printed as form tables and saved with the user.
 * They live alongside Atelier > Members, which edits the same meta.
 *
 * @package Axellcore_Atelierclub
 */

namespace Axellcore_Atelierclub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Member meta fields on the user edit screens.
 */
final class Member_Profile {

	/**
	 * Prefix of the form fields' names and ids (the meta keys stay as they are).
	 */
	const PREFIX = 'aa_member_';

	/**
	 * Singleton instance.
	 *
	 * @var Member_Profile|null
	 */
	private static $instance = null;

	/**
	 * Errors of the last save, reported by WordPress with its own.
	 *
	 * @var \WP_Error|null
	 */
	private $errors = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Member_Profile
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
	 * Register hooks: only the user edit screens load the fields.
	 */
	public function register_hooks() {
		add_action( 'load-user-edit.php', array( $this, 'load' ) );
		add_action( 'load-profile.php', array( $this, 'load' ) );
	}

	/**
	 * Show and save the fields with the user.
	 */
	public function load() {
		add_action( 'show_user_profile', array( $this, 'add_member_meta_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'add_member_meta_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_member_meta_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_member_meta_fields' ) );
		add_action( 'user_profile_update_errors', array( $this, 'report_errors' ) );
	}

	/**
	 * Whether the current user edits these fields for a user: who manages
	 * members: who can edit that user, as Atelier > Members.
	 *
	 * @param int $user_id User being edited.
	 * @return bool
	 */
	private static function can_edit( $user_id ) {
		/**
		 * Whether the current user can see and edit the member fields of a user.
		 *
		 * @param bool $can     Default: edit_user (as Atelier > Members).
		 * @param int  $user_id User being edited.
		 */
		return (bool) apply_filters( 'axellcore_atelierclub_current_user_can_edit_member_meta_fields', current_user_can( 'edit_user', $user_id ), $user_id );
	}

	/**
	 * The member fields, by fieldset: meta key => label, description, type
	 * (select or text), options.
	 *
	 * @param int $user_id User being edited (the state field depends on its country).
	 * @return array<string,array{title:string,fields:array<string,array<string,mixed>>}>
	 */
	public function get_member_meta_fields( $user_id = 0 ) {
		$labels    = Members_Export::columns();
		$country   = self::stored_country( $user_id );
		$resellers = array();
		foreach ( Members::RESELLER_FIELDS as $index => $field ) {
			$resellers[ $field . '_title' ] = array(
				/* translators: %d: partner store position, 1 to 5. */
				'label'       => sprintf( __( 'Partner store %d', 'axellcore-atelierclub' ), $index + 1 ),
				'description' => 0 === $index ? __( 'Changing a store\'s text unlinks it from the registered reseller.', 'axellcore-atelierclub' ) : '',
			);
		}

		/**
		 * Member fields on the user edit screens, by fieldset.
		 *
		 * @param array $fieldsets Fieldsets.
		 * @param int   $user_id   User being edited.
		 */
		return apply_filters(
			'axellcore_atelierclub_member_meta_fields',
			array(
				'authorship' => array(
					'title'  => __( 'Atelier: authorship', 'axellcore-atelierclub' ),
					'fields' => array(
						'company'                   => array( 'label' => $labels['company'] ),
						'phone'                     => array( 'label' => $labels['phone'] ),
						'professional_registration' => array( 'label' => $labels['professional_registration'] ),
						'primary_focus'             => array(
							'label'   => $labels['primary_focus'],
							'type'    => 'select',
							'options' => array( '' => __( 'Select an option…', 'axellcore-atelierclub' ) ) + Admin_Rest::PRIMARY_FOCUS_OPTIONS,
						),
					),
				),
				'document'   => array(
					'title'  => __( 'Atelier: document', 'axellcore-atelierclub' ),
					'fields' => array(
						'profile_type'  => array(
							'label'   => $labels['profile_type'],
							'type'    => 'select',
							'options' => array( '' => __( 'Select an option…', 'axellcore-atelierclub' ) ) + Members_Export::profile_types(),
						),
						'br_revenue_id' => array(
							'label'       => $labels['br_revenue_id'],
							'description' => __( 'Checked for its check digits and the registration type, and unique among members.', 'axellcore-atelierclub' ),
						),
					),
				),
				'address'    => array(
					'title'  => __( 'Atelier: office address', 'axellcore-atelierclub' ),
					'fields' => array(
						'country'        => array(
							'label'   => $labels['country'],
							'type'    => 'select',
							'options' => Locations::instance()->countries(),
						),
						'postal'         => array( 'label' => $labels['postal'] ),
						'address_street' => array( 'label' => $labels['address_street'] ),
						'address_number' => array( 'label' => $labels['address_number'] ),
						'address_2'      => array( 'label' => $labels['address_2'] ),
						'neighborhood'   => array( 'label' => $labels['neighborhood'] ),
						'landmark'       => array( 'label' => $labels['landmark'] ),
						'state'          => 'BR' === $country
							? array(
								'label'   => $labels['state'],
								'type'    => 'select',
								'options' => array( '' => '—' ) + Locations::instance()->states(),
							)
							: array( 'label' => $labels['state'] ),
						'city'           => array(
							'label'       => $labels['city'],
							'description' => __( 'In Brazil, a city of the chosen state.', 'axellcore-atelierclub' ),
						),
					),
				),
				'resellers'  => array(
					'title'  => __( 'Atelier: partner stores', 'axellcore-atelierclub' ),
					'fields' => $resellers,
				),
			),
			$user_id
		);
	}

	/**
	 * Print the fieldsets, as WooCommerce prints the customer's.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function add_member_meta_fields( $user ) {
		if ( ! $user instanceof \WP_User || ! self::can_edit( $user->ID ) ) {
			return;
		}
		foreach ( $this->get_member_meta_fields( $user->ID ) as $fieldset_key => $fieldset ) {
			printf( '<h2>%s</h2><table class="form-table" id="%s">', esc_html( $fieldset['title'] ), esc_attr( 'fieldset-aa-' . $fieldset_key ) );
			foreach ( $fieldset['fields'] as $key => $field ) {
				$id    = self::PREFIX . $key;
				$value = self::value( $user->ID, $key );
				echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . '</label></th><td>';
				if ( 'select' === ( $field['type'] ?? '' ) ) {
					echo '<select name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" style="width: 25em;">';
					foreach ( (array) $field['options'] as $option => $label ) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $option ), selected( $value, (string) $option, false ), esc_html( (string) $label ) );
					}
					echo '</select>';
				} else {
					printf( '<input type="text" name="%1$s" id="%1$s" value="%2$s" class="regular-text" />', esc_attr( $id ), esc_attr( $value ) );
				}
				if ( ! empty( $field['description'] ) ) {
					echo '<p class="description">' . wp_kses_post( (string) $field['description'] ) . '</p>';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
	}

	/**
	 * Save the fields with the rules of the form and Atelier > Members: the
	 * CPF/CNPJ checked and unique, the city one of its state. A field that
	 * fails keeps its value, and WordPress shows why (report_errors()).
	 *
	 * @param int $user_id User being saved.
	 */
	public function save_member_meta_fields( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! self::can_edit( $user_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WordPress checked the user form's nonce (update-user_{id}) before these hooks.
		$posted = static function ( $key ) {
			$name = self::PREFIX . $key;
			return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : null;
		};
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$this->errors = new \WP_Error();
		$fields       = array();
		foreach ( $this->get_member_meta_fields( $user_id ) as $fieldset ) {
			$fields += $fieldset['fields'];
		}

		// Plain text and selects (a select keeps only its own options).
		foreach ( $fields as $key => $field ) {
			if ( in_array( $key, array( 'br_revenue_id', 'state', 'city' ), true ) || 0 === strpos( $key, 'reseller' ) ) {
				continue;
			}
			$value = $posted( $key );
			if ( null === $value || ( 'select' === ( $field['type'] ?? '' ) && ! array_key_exists( $value, (array) $field['options'] ) ) ) {
				continue;
			}
			update_user_meta( $user_id, $key, $value );
		}

		$document = $posted( 'br_revenue_id' );
		if ( null !== $document ) {
			$profile_type = $posted( 'profile_type' );
			$valid        = Members::validate_document_for( $user_id, $document, null !== $profile_type ? $profile_type : (string) get_user_meta( $user_id, 'profile_type', true ) );
			if ( is_wp_error( $valid ) ) {
				$this->errors->add( $valid->get_error_code(), $valid->get_error_message() );
			} else {
				update_user_meta( $user_id, 'br_revenue_id', $valid );
			}
		}

		$state = $posted( 'state' );
		$city  = $posted( 'city' );
		if ( null !== $state || null !== $city ) {
			if ( '' === (string) $state && '' === (string) $city ) {
				update_user_meta( $user_id, 'state', '' );
				update_user_meta( $user_id, 'city', '' );
			} else {
				$location = Members::location( self::stored_country( $user_id ), (string) $state, (string) $city );
				if ( is_wp_error( $location ) ) {
					$this->errors->add( $location->get_error_code(), $location->get_error_message() );
				} else {
					update_user_meta( $user_id, 'state', $location[0] );
					update_user_meta( $user_id, 'city', $location[1] );
				}
			}
		}

		foreach ( Members::RESELLER_FIELDS as $field ) {
			$title = $posted( $field . '_title' );
			if ( null === $title ) {
				continue;
			}
			if ( '' === $title ) {
				delete_user_meta( $user_id, $field );
				delete_user_meta( $user_id, $field . '_title' );
			} elseif ( (string) get_user_meta( $user_id, $field . '_title', true ) !== $title ) {
				// New text: a store informed by name, no longer the registered reseller.
				update_user_meta( $user_id, $field, 0 );
				update_user_meta( $user_id, $field . '_title', $title );
			}
		}
	}

	/**
	 * Add the save's errors to WordPress's, which it shows above the form.
	 *
	 * @param \WP_Error $errors WordPress's errors for the user form.
	 */
	public function report_errors( $errors ) {
		if ( ! $this->errors instanceof \WP_Error || ! $errors instanceof \WP_Error ) {
			return;
		}
		foreach ( $this->errors->get_error_codes() as $code ) {
			$errors->add( $code, $this->errors->get_error_message( $code ) );
		}
	}

	/**
	 * A member field's stored value (the country defaults to Brazil).
	 *
	 * @param int    $user_id User.
	 * @param string $key     Meta key.
	 * @return string
	 */
	private static function value( $user_id, $key ) {
		return 'country' === $key ? self::stored_country( $user_id ) : (string) get_user_meta( $user_id, $key, true );
	}

	/**
	 * The member's country code, Brazil by default (as the form).
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private static function stored_country( $user_id ) {
		$country = $user_id ? strtoupper( (string) get_user_meta( $user_id, 'country', true ) ) : '';
		return '' !== $country ? $country : 'BR';
	}
}
