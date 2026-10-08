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
	 * User the screen shows (user-edit.php's user_id, or the current user).
	 *
	 * @var int
	 */
	private $screen_user = 0;

	/**
	 * Values sent in the last save, shown again when it fails.
	 *
	 * @var array<string,mixed>
	 */
	private $posted = array();

	/**
	 * Fields to write once the save has no errors, through Members::put()
	 * (null or '' deletes).
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which user the screen shows, no action.
		$this->screen_user = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : get_current_user_id();
		add_filter( 'woocommerce_customer_meta_fields', array( $this, 'woocommerce_customer_fields' ) );
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
		wp_interactivity_state(
			'axell/member-city',
			array( 'citiesUrl' => rest_url( Rest::NAMESPACE . '/cities' ) )
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
	 * The member fields, by fieldset, in the order of the form: field =>
	 * label, description, type (select, city, resellers or text), options,
	 * disabled, mask (phone, postal, document). Name, e-mail and site are
	 * WordPress's own fields on the same screen.
	 *
	 * @param int $user_id User being edited.
	 * @return array<string,array{title:string,fields:array<string,array<string,mixed>>}>
	 */
	public function get_member_meta_fields( $user_id = 0 ) {
		$labels    = array_merge( Members_Export::columns(), self::woocommerce_labels() );
		$fieldsets = array(
			'authorship' => array(
				'title'  => __( 'Atelier: Authorship', 'axellcore-atelierclub' ),
				'fields' => array(
					'company'                   => array( 'label' => $labels['company'] ),
					'phone'                     => array(
						'label' => $labels['phone'],
						'mask'  => 'phone',
					),
					'professional_registration' => array( 'label' => $labels['professional_registration'] ),
					'primary_focus'             => array(
						'label'   => $labels['primary_focus'],
						'type'    => 'select',
						'options' => array( '' => __( 'Select an option…', 'axellcore-atelierclub' ) ) + Admin_Rest::PRIMARY_FOCUS_OPTIONS,
					),
				),
			),
			'document'   => array(
				'title'  => __( 'Atelier: Document', 'axellcore-atelierclub' ),
				'fields' => array(
					// Never stored: it follows the CPF/CNPJ (Members::profile_type_of()).
					'profile_type'  => array(
						'label'    => $labels['profile_type'],
						'type'     => 'select',
						'disabled' => true,
						'options'  => array( '' => '' ) + Members_Export::profile_types(),
					),
					'br_revenue_id' => array(
						'label'       => $labels['br_revenue_id'],
						'mask'        => 'document',
						'description' => __( 'Checked for its check digits and unique among members; the registration type follows it.', 'axellcore-atelierclub' ),
					),
				),
			),
			'address'    => array(
				'title'  => __( 'Atelier: Office address', 'axellcore-atelierclub' ),
				'fields' => array(
					// Brazil only: shown with every country, never editable.
					'country'        => array(
						'label'    => $labels['country'],
						'type'     => 'select',
						'disabled' => true,
						'options'  => Locations::instance()->countries(),
					),
					'address_street' => array( 'label' => $labels['address_street'] ),
					'address_number' => array( 'label' => $labels['address_number'] ),
					'address_2'      => array( 'label' => $labels['address_2'] ),
					'neighborhood'   => array( 'label' => $labels['neighborhood'] ),
					'landmark'       => array( 'label' => $labels['landmark'] ),
					'state'          => array(
						'label'      => $labels['state'],
						'type'       => 'select',
						'searchable' => true,
						'options'    => array( '' => '' ) + Locations::instance()->states(),
					),
					'city'           => array(
						'label' => $labels['city'],
						'type'  => 'city',
					),
					'postal'         => array(
						'label' => $labels['postal'],
						'mask'  => 'postal',
					),
				),
			),
			'resellers'  => array(
				'title'  => __( 'Atelier: Partner stores', 'axellcore-atelierclub' ),
				'fields' => array(
					'resellers' => array(
						'label'       => $labels['resellers'],
						'type'        => 'resellers',
						'description' => __( 'Search the registered resellers by name. Leaving a store empty moves the next ones up.', 'axellcore-atelierclub' ),
					),
				),
			),
		);

		// With WooCommerce, a customer who is not a member keeps its billing
		// fields in WooCommerce's own section: here only what it lacks.
		if ( self::woocommerce() && ! self::is_member( $user_id ) ) {
			foreach ( $fieldsets as $key => $fieldset ) {
				$fieldsets[ $key ]['fields'] = array_diff_key( $fieldset['fields'], self::woocommerce_fields() );
			}
		}

		/**
		 * Member fields on the user edit screens, by fieldset.
		 *
		 * @param array $fieldsets Fieldsets.
		 * @param int   $user_id   User being edited.
		 */
		return apply_filters( 'axellcore_atelierclub_member_meta_fields', $fieldsets, $user_id );
	}

	/**
	 * The labels of the fields WooCommerce also has, as its customer screen
	 * names them (and its translations: the "WooCommerce field" context).
	 *
	 * @return array<string,string>
	 */
	public static function woocommerce_labels() {
		return array(
			'company'        => _x( 'Company', 'WooCommerce field', 'axellcore-atelierclub' ),
			'phone'          => _x( 'Phone', 'WooCommerce field', 'axellcore-atelierclub' ),
			'country'        => _x( 'Country / Region', 'WooCommerce field', 'axellcore-atelierclub' ),
			'address_street' => _x( 'Address line 1', 'WooCommerce field', 'axellcore-atelierclub' ),
			'address_2'      => _x( 'Address line 2', 'WooCommerce field', 'axellcore-atelierclub' ),
			'state'          => _x( 'State / County', 'WooCommerce field', 'axellcore-atelierclub' ),
			'city'           => _x( 'City', 'WooCommerce field', 'axellcore-atelierclub' ),
			'postal'         => _x( 'Postcode / ZIP', 'WooCommerce field', 'axellcore-atelierclub' ),
		);
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	private static function woocommerce() {
		/**
		 * Whether WooCommerce is active, for the member fields it shares.
		 *
		 * @param bool $active Default: the WooCommerce class exists.
		 */
		return (bool) apply_filters( 'axellcore_atelierclub_woocommerce_active', class_exists( 'WooCommerce' ) );
	}

	/**
	 * Whether a user is a member (approved or pending).
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	private static function is_member( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		return $user instanceof \WP_User && (bool) array_intersect( array_keys( Member::roles() ), (array) $user->roles );
	}

	/**
	 * The member fields WooCommerce's billing section also has (field =>
	 * its meta key).
	 *
	 * @return array<string,string>
	 */
	private static function woocommerce_fields() {
		return array_diff_key( Members::META, array_flip( array( 'address_number', 'neighborhood' ) ) );
	}

	/**
	 * WooCommerce's billing section, for a member: without the fields this
	 * screen edits (and the billing name, kept from the user's name).
	 *
	 * @param array $fieldsets WooCommerce's customer fieldsets.
	 * @return array
	 */
	public function woocommerce_customer_fields( $fieldsets ) {
		if ( ! is_array( $fieldsets ) || ! isset( $fieldsets['billing']['fields'] ) || ! self::is_member( $this->screen_user ) ) {
			return $fieldsets;
		}
		$ours                           = array_merge( array_values( self::woocommerce_fields() ), array( 'billing_first_name', 'billing_last_name' ) );
		$fieldsets['billing']['fields'] = array_diff_key( $fieldsets['billing']['fields'], array_flip( $ours ) );
		return $fieldsets;
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
			if ( ! $fieldset['fields'] ) {
				continue;
			}
			printf( '<h2>%s</h2><table class="form-table" id="%s">', esc_html( $fieldset['title'] ), esc_attr( 'fieldset-aa-' . $fieldset_key ) );
			foreach ( $fieldset['fields'] as $key => $field ) {
				// The stores mark the wrong position themselves (print_resellers()).
				$id    = self::PREFIX . $key;
				$type  = $field['type'] ?? 'text';
				$error = 'resellers' === $type ? '' : ( $this->errors[ $key ][1] ?? '' );
				$label = 'resellers' === $type ? $id . '_0' : $id;
				$attrs = '' !== $error ? sprintf( ' aria-invalid="true" aria-describedby="%s-error"', esc_attr( $id ) ) : '';
				if ( ! empty( $field['disabled'] ) ) {
					$attrs .= ' disabled';
				}

				printf( '<tr%s><th><label for="%s" id="%s-label">%s</label></th><td>', '' !== $error ? ' class="form-required form-invalid"' : '', esc_attr( $label ), esc_attr( $id ), esc_html( (string) $field['label'] ) );
				if ( 'resellers' === $type ) {
					$this->print_resellers( $user->ID );
				} elseif ( 'city' === $type ) {
					$this->print_city( $id, $this->value( $user->ID, $key ), $attrs );
				} elseif ( 'select' === $type && ! empty( $field['searchable'] ) ) {
					$this->print_searchable_select( $id, $key, (array) $field['options'], $this->value( $user->ID, $key ), $attrs );
				} elseif ( 'select' === $type ) {
					$value = $this->value( $user->ID, $key );
					printf( '<select name="%1$s" id="%1$s" style="width: 25em;"%2$s%3$s>', esc_attr( $id ), $attrs, 'state' === $key ? ' data-wp-interactive="axell/member-city" data-wp-on--change="actions.onState"' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is escaped above.
					foreach ( (array) $field['options'] as $option => $option_label ) {
						printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $option ), selected( $value, (string) $option, false ), esc_html( (string) $option_label ) );
					}
					echo '</select>';
				} else {
					$mask = (string) ( $field['mask'] ?? '' );
					printf(
						'<input type="text" name="%1$s" id="%1$s" value="%2$s" class="regular-text"%3$s%4$s />',
						esc_attr( $id ),
						esc_attr( $this->value( $user->ID, $key ) ),
						$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
						'' !== $mask ? sprintf( ' inputmode="%s" data-wp-interactive="axell/member-fields" data-wp-on--input="actions.mask" data-mask="%s"', 'document' === $mask ? 'text' : 'numeric', esc_attr( $mask ) ) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
					);
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
	 * A select with a search, as WooCommerce's (selectWoo) on its state
	 * field: the native select stays (and is what is sent; without
	 * JavaScript it is the field), and a button opens the options with a
	 * filter over them.
	 *
	 * @param string               $id      Field id and name.
	 * @param string               $key     Field.
	 * @param array<string,string> $options Label by value.
	 * @param string               $value   Selected value.
	 * @param string               $attrs   Extra attributes of the select (escaped).
	 */
	private function print_searchable_select( $id, $key, array $options, $value, $attrs ) {
		$items = array();
		foreach ( $options as $option => $label ) {
			if ( '' !== (string) $option ) {
				$items[] = array(
					'value' => (string) $option,
					'label' => (string) $label,
				);
			}
		}
		$context = array(
			'value'  => (string) $value,
			'label'  => (string) ( $options[ $value ] ?? '' ),
			'items'  => $items,
			'query'  => '',
			'open'   => false,
			'active' => -1,
			'ready'  => false,
			'listId' => $id . '-options',
		);
		printf(
			'<div class="aa-member-select" data-wp-interactive="axell/member-select" data-wp-context="%1$s" data-wp-init="callbacks.init" data-wp-on--focusout="actions.onFocusOut" data-wp-on--keydown="actions.onKeydown">',
			esc_attr( (string) wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
		);
		// The state's change also clears the city (axell/member-city).
		printf(
			'<select name="%1$s" id="%1$s" style="width: 25em;"%2$s data-wp-bind--hidden="context.ready" data-wp-bind--value="context.value"%3$s>',
			esc_attr( $id ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
			'state' === $key ? ' data-wp-on--change="axell/member-city::actions.onState"' : ''
		);
		foreach ( $options as $option => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $option ), selected( $value, (string) $option, false ), esc_html( (string) $label ) );
		}
		echo '</select>';
		printf(
			'<button type="button" class="aa-member-select__toggle" hidden aria-haspopup="listbox" aria-expanded="false" aria-labelledby="%1$s-label %1$s-toggle" id="%1$s-toggle" data-wp-bind--hidden="!context.ready" data-wp-bind--aria-expanded="context.open" data-wp-on--click="actions.toggle"><span data-wp-text="state.shown">%2$s</span></button>',
			esc_attr( $id ),
			esc_html( (string) ( $options[ $value ] ?? '' ) )
		);
		printf(
			'<div class="aa-member-select__popup" hidden data-wp-bind--hidden="!context.open">'
				. '<input type="search" class="aa-member-select__search" role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="%1$s-options" aria-label="%2$s" autocomplete="off" data-wp-bind--value="context.query" data-wp-bind--aria-activedescendant="state.activeId" data-wp-on--input="actions.onSearch"/>'
				. '<ul id="%1$s-options" class="aa-member-store__list" role="listbox" tabindex="-1">'
				. '<template data-wp-each--item="state.filtered" data-wp-each-key="context.item.value"><li role="option" data-wp-bind--id="state.itemId" data-wp-bind--aria-selected="state.isActive" data-wp-class--is-current="state.isCurrent" data-wp-text="context.item.label" data-wp-on--mousedown="actions.pick"></li></template>'
				. '<li class="aa-member-select__none" data-wp-bind--hidden="state.hasResults" hidden>%3$s</li>'
				. '</ul></div></div>',
			esc_attr( $id ),
			esc_attr__( 'Search', 'axellcore-atelierclub' ),
			esc_html__( 'No matches found', 'axellcore-atelierclub' )
		);
	}

	/**
	 * The city: a search over the cities of the chosen state (the same list
	 * and endpoint as the form), as the form's city field.
	 *
	 * @param string $id    Field id and name.
	 * @param string $value City.
	 * @param string $attrs Extra attributes (escaped).
	 */
	private function print_city( $id, $value, $attrs ) {
		printf(
			'<div class="aa-member-city" data-wp-interactive="axell/member-city" data-wp-context="%5$s" data-wp-on--focusout="actions.onFocusOut">'
				. '<input type="text" name="%1$s" id="%1$s" value="%2$s" class="regular-text" role="combobox" aria-autocomplete="list" aria-controls="%1$s-list" aria-expanded="false" autocomplete="off"%3$s'
				. ' data-wp-bind--value="context.value" data-wp-bind--aria-expanded="state.isOpen" data-wp-bind--aria-activedescendant="state.activeId" data-wp-on--input="actions.onInput" data-wp-on--keydown="actions.onKeydown"/>'
				. '<ul id="%1$s-list" class="aa-member-store__list" role="listbox" hidden tabindex="-1" aria-label="%4$s" data-wp-bind--hidden="!state.isOpen">'
				. '<template data-wp-each--option="context.options" data-wp-each-key="context.option"><li role="option" data-wp-bind--id="state.optionId" data-wp-bind--aria-selected="state.isActive" data-wp-text="context.option" data-wp-on--mousedown="actions.pick"></li></template>'
				. '</ul></div>',
			esc_attr( $id ),
			esc_attr( $value ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
			esc_attr__( 'Cities', 'axellcore-atelierclub' ),
			esc_attr(
				(string) wp_json_encode(
					array(
						// Bound, or a new render would put back the value it was loaded with.
						'value'   => $value,
						'options' => array(),
						'open'    => false,
						'active'  => -1,
					)
				)
			)
		);
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
				esc_attr__( 'Store name · city', 'axellcore-atelierclub' ),
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

		// Brazil only: the country is never sent (disabled); another one is refused.
		if ( isset( $fields['country'] ) ) {
			if ( isset( $this->posted['country'] ) && 'BR' !== strtoupper( $this->posted['country'] ) ) {
				$this->errors['country'] = array( 'aa_invalid_country', __( 'Only Brazil is accepted.', 'axellcore-atelierclub' ) );
			}
			$this->pending['country'] = 'BR';
		}

		foreach ( $fields as $key => $field ) {
			if ( ! array_key_exists( $key, $this->posted ) || ! empty( $field['disabled'] ) || in_array( $key, array( 'br_revenue_id', 'state', 'city', 'resellers' ), true ) ) {
				continue;
			}
			if ( 'phone' === $key && '' !== $this->posted[ $key ] && ! preg_match( '/^\d{2}(9\d{8}|[2-5]\d{7})$/', Format::phone_national( $this->posted[ $key ] ) ) ) {
				$this->errors[ $key ] = array( 'aa_invalid_phone', __( 'Enter a phone number with area code.', 'axellcore-atelierclub' ) );
				continue;
			}
			if ( 'postal' === $key && '' !== $this->posted[ $key ] && ! preg_match( '/^\d{8}$/', Format::digits( $this->posted[ $key ] ) ) ) {
				$this->errors[ $key ] = array( 'aa_invalid_postal', __( 'Enter a CEP with 8 digits.', 'axellcore-atelierclub' ) );
				continue;
			}
			if ( 'select' === ( $field['type'] ?? '' ) && ! array_key_exists( $this->posted[ $key ], (array) $field['options'] ) ) {
				$this->errors[ $key ] = array( 'aa_invalid_option', __( 'Choose an option from the list.', 'axellcore-atelierclub' ) );
				continue;
			}
			$this->pending[ $key ] = $this->posted[ $key ];
		}

		if ( isset( $this->posted['br_revenue_id'] ) ) {
			// The type follows the number (11 characters CPF, 14 CNPJ).
			$valid = Members::validate_document_for( $user_id, $this->posted['br_revenue_id'], '' );
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
				$location = Members::location( 'BR', $state, $city );
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
		foreach ( $this->pending as $field => $value ) {
			Members::put( $this->user_id, $field, (string) $value );
		}
	}

	/**
	 * A member field's value as shown: what was sent when the save failed,
	 * the stored one otherwise, with its mask (phone, CEP, CPF/CNPJ). The
	 * country is always Brazil; the profile type follows the CPF/CNPJ.
	 *
	 * @param int    $user_id User.
	 * @param string $key     Field.
	 * @return string
	 */
	private function value( $user_id, $key ) {
		if ( 'country' === $key ) {
			return 'BR';
		}
		if ( 'profile_type' === $key ) {
			return Members::profile_type_of( $this->value( $user_id, 'br_revenue_id' ) );
		}
		if ( isset( $this->posted[ $key ] ) && is_string( $this->posted[ $key ] ) ) {
			return $this->posted[ $key ];
		}
		$value = Members::get( $user_id, $key );
		switch ( $key ) {
			case 'phone':
				return Format::phone( $value );
			case 'postal':
				return Format::postcode( $value );
			case 'br_revenue_id':
				return Format::document( $value );
		}
		return $value;
	}
}
