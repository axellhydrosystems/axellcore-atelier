<?php
/**
 * Member fields on the user edit screens (user-edit.php, profile.php), in
 * the mold of WooCommerce's customer fields (WC_Admin_Profile): fieldsets of
 * meta fields, filterable, printed as form tables and saved with the user.
 * They live alongside Atelier > Members, which edits the same meta.
 *
 * Saving is all or nothing: the fields are checked first and written only
 * when neither they nor WordPress's own fields have an error. On an error
 * WordPress shows the form again in the same request, so the fields show
 * what was sent, the wrong ones marked.
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
	 * Script module handle of the partner stores picker.
	 */
	const HANDLE = 'axellcore-atelierclub-member-profile';

	/**
	 * Singleton instance.
	 *
	 * @var Member_Profile|null
	 */
	private static $instance = null;

	/**
	 * User of the last save (0: none in this request).
	 *
	 * @var int
	 */
	private $user_id = 0;

	/**
	 * Values sent in the last save, shown again when it fails.
	 *
	 * @var array<string,mixed>
	 */
	private $posted = array();

	/**
	 * Meta to write once the save has no errors (null deletes).
	 *
	 * @var array<string,mixed>
	 */
	private $pending = array();

	/**
	 * Errors of the last save, by field: key => array( code, message ).
	 * Partner stores use "resellers:{position}".
	 *
	 * @var array<string,array{0:string,1:string}>
	 */
	private $errors = array();

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
		// Last, to know whether WordPress's own fields have errors too.
		add_action( 'user_profile_update_errors', array( $this, 'report_errors' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * The partner stores picker (Interactivity API) and its styles.
	 */
	public function enqueue() {
		wp_enqueue_style( self::HANDLE, AXELLCORE_ATELIERCLUB_URL . 'build/admin/member-profile/style-index.css', array(), AXELLCORE_ATELIERCLUB_VERSION );
		wp_enqueue_script_module( self::HANDLE, AXELLCORE_ATELIERCLUB_URL . 'build/admin/member-profile/view.js', array( array( 'id' => '@wordpress/interactivity' ) ), AXELLCORE_ATELIERCLUB_VERSION );
		wp_interactivity_state(
			'axell/member-stores',
			array(
				'optionsUrl' => rest_url( Rest::NAMESPACE . '/options' ),
				'postType'   => Resellers::POST_TYPE,
				'template'   => Form_Directives::RESELLER_TEMPLATE,
			)
		);
	}

	/**
	 * Whether the current user edits these fields for a user: who can edit
	 * that user, as Atelier > Members.
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
	 * (select, resellers or text), options.
	 *
	 * @param int $user_id User being edited (the state field depends on its country).
	 * @return array<string,array{title:string,fields:array<string,array<string,mixed>>}>
	 */
	public function get_member_meta_fields( $user_id = 0 ) {
		$labels  = Members_Export::columns();
		$country = $this->country( $user_id );

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
					'fields' => array(
						'resellers' => array(
							'label'       => $labels['resellers'],
							'type'        => 'resellers',
							'description' => __( 'Search the registered resellers by name. Leaving a store empty moves the next ones up.', 'axellcore-atelierclub' ),
						),
					),
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
				// The stores mark the wrong position themselves (print_resellers()).
				$id    = self::PREFIX . $key;
				$type  = $field['type'] ?? 'text';
				$error = 'resellers' === $type ? '' : ( $this->errors[ $key ][1] ?? '' );
				$label = 'resellers' === $type ? $id . '_0' : $id;
				$attrs = '' !== $error ? sprintf( ' aria-invalid="true" aria-describedby="%s-error"', esc_attr( $id ) ) : '';

				printf( '<tr%s><th><label for="%s">%s</label></th><td>', '' !== $error ? ' class="form-required form-invalid"' : '', esc_attr( $label ), esc_html( (string) $field['label'] ) );
				if ( 'resellers' === $type ) {
					$this->print_resellers( $user->ID );
				} elseif ( 'select' === $type ) {
					$value = $this->value( $user->ID, $key );
					printf( '<select name="%1$s" id="%1$s" style="width: 25em;"%2$s>', esc_attr( $id ), $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is escaped above.
					foreach ( (array) $field['options'] as $option => $option_label ) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $option ), selected( $value, (string) $option, false ), esc_html( (string) $option_label ) );
					}
					echo '</select>';
				} else {
					printf( '<input type="text" name="%1$s" id="%1$s" value="%2$s" class="regular-text"%3$s />', esc_attr( $id ), esc_attr( $this->value( $user->ID, $key ) ), $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is escaped above.
				}
				if ( '' !== $error ) {
					printf( '<p class="description aa-member-error" id="%s-error">%s</p>', esc_attr( $id ), esc_html( $error ) );
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
	 * The partner stores: one search field per position, as on the form. The
	 * first is always shown, the next once the one before has a store.
	 *
	 * @param int $user_id User being edited.
	 */
	private function print_resellers( $user_id ) {
		$slots = $this->reseller_slots( $user_id );
		$html  = sprintf(
			'<div class="aa-member-stores" data-wp-interactive="axell/member-stores" data-wp-context="%s">',
			esc_attr( (string) wp_json_encode( array( 'slots' => $slots ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
		);
		foreach ( $slots as $index => $slot ) {
			$id     = self::PREFIX . 'resellers_' . $index;
			$error  = $this->errors[ 'resellers:' . $index ][1] ?? '';
			$hidden = $index > 0 && '' === $slot['title'] && '' === $slots[ $index - 1 ]['title'];
			$html  .= sprintf(
				'<div class="aa-member-store%1$s" data-wp-context="%2$s" data-wp-bind--hidden="state.isHidden" data-wp-class--is-invalid="state.isInvalid" data-wp-on--focusout="actions.onFocusOut"%3$s>'
					. '<input type="text" id="%4$s" name="%5$s[%6$d][title]" value="%7$s" class="regular-text" role="combobox" aria-autocomplete="list" aria-controls="%4$s-list" aria-expanded="false" autocomplete="off" placeholder="%8$s"%9$s%10$s'
					. ' data-wp-bind--aria-invalid="state.isInvalid"'
					. ' data-wp-bind--value="state.title" data-wp-bind--aria-expanded="state.isOpen" data-wp-bind--aria-activedescendant="state.activeId" data-wp-on--input="actions.onInput" data-wp-on--keydown="actions.onKeydown"/>'
					. '<input type="hidden" name="%5$s[%6$d][id]" value="%11$s" data-wp-bind--value="state.id"/>'
					. '<span class="aa-member-store__pending"%12$s data-wp-bind--hidden="!state.isPending">%13$s <a href="%14$s">%15$s</a></span>'
					. '<button type="button" class="button-link aa-member-store__remove"%16$s data-wp-bind--hidden="!state.isFilled" data-wp-on--click="actions.remove">%17$s</button>'
					. '<ul id="%4$s-list" class="aa-member-store__list" role="listbox" hidden tabindex="-1" data-wp-bind--hidden="!state.isOpen">'
					. '<template data-wp-each--option="state.options" data-wp-each-key="context.option.id"><li role="option" data-wp-bind--id="state.optionId" data-wp-bind--aria-selected="state.isActive" data-wp-text="context.option.label" data-wp-on--mousedown="actions.pick"></li></template>'
					. '</ul>%18$s</div>',
				'' !== $error ? ' is-invalid' : '',
				esc_attr( (string) wp_json_encode( array( 'index' => $index ) ) ),
				$hidden ? ' hidden' : '',
				esc_attr( $id ),
				esc_attr( self::PREFIX . 'resellers' ),
				$index,
				esc_attr( $slot['title'] ),
				esc_attr__( 'Search reseller…', 'axellcore-atelierclub' ),
				/* translators: %d: partner store position, 1 to 5. */
				$index > 0 ? sprintf( ' aria-label="%s"', esc_attr( sprintf( __( 'Partner store %d', 'axellcore-atelierclub' ), $index + 1 ) ) ) : '',
				'' !== $error ? sprintf( ' aria-invalid="true" aria-describedby="%s-error"', esc_attr( $id ) ) : '',
				esc_attr( $slot['id'] ),
				$slot['pending'] ? '' : ' hidden',
				esc_html__( 'Pending curation.', 'axellcore-atelierclub' ),
				esc_url( $slot['url'] ),
				esc_html__( 'Open store', 'axellcore-atelierclub' ),
				'' === $slot['title'] ? ' hidden' : '',
				esc_html__( 'Remove', 'axellcore-atelierclub' ),
				'' !== $error ? sprintf( '<p class="description aa-member-error" id="%s-error" data-wp-bind--hidden="!state.isInvalid">%s</p>', esc_attr( $id ), esc_html( $error ) ) : ''
			);
		}
		$html .= '</div>';
		// Not processed on the server: the derived state lives in the browser,
		// and the markup already holds the initial values (and works without it).
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped above.
	}

	/**
	 * The five positions as shown: what was sent when the save failed, the
	 * stored stores otherwise.
	 *
	 * @param int $user_id User.
	 * @return list<array{id:string,title:string,pending:bool,url:string,options:array<int,mixed>,open:bool,active:int,invalid:bool}>
	 */
	private function reseller_slots( $user_id ) {
		$rows = isset( $this->posted['resellers'] ) ? $this->posted['resellers'] : self::stored_resellers( $user_id );
		$out  = array();
		foreach ( array_keys( Members::RESELLER_FIELDS ) as $index ) {
			$id     = (string) ( $rows[ $index ]['id'] ?? '' );
			$status = '' !== $id && '0' !== $id ? (string) get_post_status( (int) $id ) : '';
			$out[]  = array(
				'id'      => $id,
				'title'   => (string) ( $rows[ $index ]['title'] ?? '' ),
				'pending' => 'pending' === $status,
				'url'     => 'pending' === $status ? (string) get_edit_post_link( (int) $id, 'raw' ) : '',
				'options' => array(),
				'open'    => false,
				'active'  => -1,
				'invalid' => isset( $this->errors[ 'resellers:' . $index ] ),
			);
		}
		return $out;
	}

	/**
	 * Check the fields with the rules of the form and Atelier > Members (the
	 * CPF/CNPJ checked and unique, the city one of its state, stores from the
	 * registered resellers). Nothing is written here: report_errors() writes
	 * it all once WordPress has checked its own fields.
	 *
	 * @param int $user_id User being saved.
	 */
	public function save_member_meta_fields( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! self::can_edit( $user_id ) ) {
			return;
		}
		$this->user_id = $user_id;
		$this->posted  = array();
		$this->pending = array();
		$this->errors  = array();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WordPress checked the user form's nonce (update-user_{id}) before these hooks.
		$fields = array();
		foreach ( $this->get_member_meta_fields( $user_id ) as $fieldset ) {
			$fields += $fieldset['fields'];
		}
		foreach ( $fields as $key => $field ) {
			$name = self::PREFIX . $key;
			if ( 'resellers' === ( $field['type'] ?? '' ) ) {
				if ( isset( $_POST[ $name ] ) && is_array( $_POST[ $name ] ) ) {
					$this->posted['resellers'] = self::posted_resellers( wp_unslash( $_POST[ $name ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by posted_resellers().
				}
			} elseif ( isset( $_POST[ $name ] ) && is_scalar( $_POST[ $name ] ) ) {
				$this->posted[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// The state field follows the country sent.
		foreach ( $this->get_member_meta_fields( $user_id ) as $fieldset ) {
			foreach ( $fieldset['fields'] as $key => $field ) {
				if ( ! array_key_exists( $key, $this->posted ) || in_array( $key, array( 'br_revenue_id', 'state', 'city', 'resellers' ), true ) ) {
					continue;
				}
				if ( 'select' === ( $field['type'] ?? '' ) && ! array_key_exists( $this->posted[ $key ], (array) $field['options'] ) ) {
					$this->errors[ $key ] = array( 'aa_invalid_option', __( 'Choose an option from the list.', 'axellcore-atelierclub' ) );
					continue;
				}
				$this->pending[ $key ] = $this->posted[ $key ];
			}
		}

		if ( isset( $this->posted['br_revenue_id'] ) ) {
			$valid = Members::validate_document_for( $user_id, $this->posted['br_revenue_id'], $this->posted['profile_type'] ?? (string) get_user_meta( $user_id, 'profile_type', true ) );
			if ( is_wp_error( $valid ) ) {
				$this->errors['br_revenue_id'] = array( (string) $valid->get_error_code(), $valid->get_error_message() );
			} else {
				$this->pending['br_revenue_id'] = $valid;
			}
		}

		if ( isset( $this->posted['state'] ) || isset( $this->posted['city'] ) ) {
			$state = (string) ( $this->posted['state'] ?? '' );
			$city  = (string) ( $this->posted['city'] ?? '' );
			if ( '' === $state && '' === $city ) {
				$this->pending['state'] = '';
				$this->pending['city']  = '';
			} else {
				$location = Members::location( $this->country( $user_id ), $state, $city );
				if ( is_wp_error( $location ) ) {
					$this->errors['city'] = array( (string) $location->get_error_code(), $location->get_error_message() );
				} else {
					$this->pending['state'] = $location[0];
					$this->pending['city']  = $location[1];
				}
			}
		}

		if ( isset( $this->posted['resellers'] ) ) {
			$this->check_resellers( $user_id, $this->posted['resellers'] );
		}
	}

	/**
	 * The stores sent: up to five rows of ID and text.
	 *
	 * @param array<mixed> $rows Raw rows.
	 * @return list<array{id:string,title:string}>
	 */
	private static function posted_resellers( $rows ) {
		$out = array();
		foreach ( array_keys( Members::RESELLER_FIELDS ) as $index ) {
			$row   = isset( $rows[ $index ] ) && is_array( $rows[ $index ] ) ? $rows[ $index ] : array();
			$out[] = array(
				'id'    => (string) absint( is_scalar( $row['id'] ?? '' ) ? $row['id'] ?? 0 : 0 ),
				'title' => sanitize_text_field( is_scalar( $row['title'] ?? '' ) ? (string) ( $row['title'] ?? '' ) : '' ),
			);
		}
		foreach ( $out as $index => $row ) {
			if ( '0' === $row['id'] ) {
				$out[ $index ]['id'] = '';
			}
		}
		return $out;
	}

	/**
	 * The member's stores as stored, by position.
	 *
	 * @param int $user_id User.
	 * @return list<array{id:string,title:string}>
	 */
	private static function stored_resellers( $user_id ) {
		$out = array();
		foreach ( Members::RESELLER_FIELDS as $field ) {
			$id    = (string) get_user_meta( $user_id, $field, true );
			$out[] = array(
				'id'    => '0' === $id ? '' : $id,
				'title' => (string) get_user_meta( $user_id, $field . '_title', true ),
			);
		}
		return $out;
	}

	/**
	 * Partner stores: a registered (published) reseller, or one the member
	 * already had (a pending one, or a store given by text on the form);
	 * nothing typed by hand. Empty positions move the next ones up, a
	 * repeated reseller counts once.
	 *
	 * @param int                                 $user_id User.
	 * @param list<array{id:string,title:string}> $rows    Stores sent.
	 */
	private function check_resellers( $user_id, $rows ) {
		$stored = self::stored_resellers( $user_id );
		$kept   = array();
		$seen   = array();
		foreach ( $rows as $index => $row ) {
			if ( '' !== $row['id'] ) {
				$id            = (int) $row['id'];
				$stored_titles = array_column( array_filter( $stored, static fn( $s ) => (string) $id === $s['id'] ), 'title' );
				if ( Resellers::POST_TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
					$title = Label_Template::render( get_post( $id ), Form_Directives::RESELLER_TEMPLATE );
				} elseif ( $stored_titles ) {
					$title = (string) $stored_titles[0];
				} else {
					$this->errors[ 'resellers:' . $index ] = array( 'aa_invalid_reseller', __( 'Choose a reseller from the list.', 'axellcore-atelierclub' ) );
					continue;
				}
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$kept[]      = array( (string) $id, $title );
			} elseif ( '' !== $row['title'] ) {
				$text_only = array_filter( $stored, static fn( $s ) => '' === $s['id'] && $s['title'] === $row['title'] );
				if ( ! $text_only ) {
					$this->errors[ 'resellers:' . $index ] = array( 'aa_invalid_reseller', __( 'Choose a reseller from the list.', 'axellcore-atelierclub' ) );
					continue;
				}
				$kept[] = array( '', $row['title'] );
			}
		}
		foreach ( Members::RESELLER_FIELDS as $index => $field ) {
			$this->pending[ $field ]            = isset( $kept[ $index ] ) ? $kept[ $index ][0] : null;
			$this->pending[ $field . '_title' ] = isset( $kept[ $index ] ) ? $kept[ $index ][1] : null;
		}
	}

	/**
	 * Add the fields' errors to WordPress's (shown above the form), and write
	 * the fields only when there are none, ours or WordPress's.
	 *
	 * @param \WP_Error $errors WordPress's errors for the user form.
	 */
	public function report_errors( $errors ) {
		if ( ! $this->user_id || ! $errors instanceof \WP_Error ) {
			return;
		}
		foreach ( $this->errors as $error ) {
			$errors->add( $error[0], $error[1] );
		}
		if ( $errors->has_errors() ) {
			return;
		}
		foreach ( $this->pending as $key => $value ) {
			if ( null === $value ) {
				delete_user_meta( $this->user_id, $key );
			} else {
				update_user_meta( $this->user_id, $key, $value );
			}
		}
	}

	/**
	 * A member field's value as shown: what was sent when the save failed,
	 * the stored one otherwise (the country defaults to Brazil).
	 *
	 * @param int    $user_id User.
	 * @param string $key     Meta key.
	 * @return string
	 */
	private function value( $user_id, $key ) {
		if ( isset( $this->posted[ $key ] ) && is_string( $this->posted[ $key ] ) ) {
			return $this->posted[ $key ];
		}
		return 'country' === $key ? $this->country( $user_id ) : (string) get_user_meta( $user_id, $key, true );
	}

	/**
	 * The member's country code: the one sent, else the stored one, Brazil
	 * by default (as the form).
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private function country( $user_id ) {
		$country = isset( $this->posted['country'] ) ? (string) $this->posted['country'] : '';
		if ( '' === $country && $user_id ) {
			$country = (string) get_user_meta( $user_id, 'country', true );
		}
		$country = strtoupper( $country );
		return '' !== $country ? $country : 'BR';
	}
}
