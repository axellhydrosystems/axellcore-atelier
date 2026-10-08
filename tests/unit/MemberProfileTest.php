<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Member_Profile;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class MemberProfileTest extends TestCase {

	/**
	 * Posts by ID: post type, status, title.
	 *
	 * @var array<int,array{0:string,1:string,2:string}>
	 */
	private const POSTS = array(
		42 => array( 'revendas', 'publish', 'Loja A' ),
		50 => array( 'revendas', 'publish', 'Nova' ),
		77 => array( 'revendas', 'pending', 'Loja pendente' ),
		99 => array( 'page', 'publish', 'Sobre' ),
	);

	/**
	 * User meta of the user being edited (ID 9).
	 *
	 * @var array<string,mixed>
	 */
	private $meta = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$_POST      = array();
		$this->meta = array(
			'billing_company' => 'Old studio',
			'reseller_ids'    => '42,77',
			'billing_cpf'     => '52998224725',
		);
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'get_user_locale' )->justReturn( 'en_US' );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		Functions\when( 'selected' )->alias(
			static function ( $a, $b ) {
				return (string) $a === (string) $b ? ' selected="selected"' : '';
			}
		);
		Functions\when( 'wp_kses_post' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'get_edit_post_link' )->justReturn( 'https://example.com/wp-admin/post.php?post=77&action=edit' );
		Functions\when( 'get_post_type' )->alias(
			static function ( $id ) {
				return self::POSTS[ $id ][0] ?? false;
			}
		);
		Functions\when( 'get_post_status' )->alias(
			static function ( $id ) {
				return self::POSTS[ $id ][1] ?? false;
			}
		);
		Functions\when( 'get_post' )->alias(
			static function ( $id ) {
				return isset( self::POSTS[ $id ] ) ? new \WP_Post( array( 'ID' => $id, 'post_type' => self::POSTS[ $id ][0], 'post_status' => self::POSTS[ $id ][1], 'post_title' => self::POSTS[ $id ][2] ) ) : null;
			}
		);
		Functions\when( 'get_the_terms' )->alias(
			static function ( $id, $taxonomy ) {
				return 'estados' === $taxonomy
					? array( (object) array( 'slug' => 'rs', 'name' => 'Rio Grande do Sul' ) )
					: array( (object) array( 'slug' => 'caxias-do-sul', 'name' => 'Caxias do Sul' ) );
			}
		);
		Functions\when( 'get_user_meta' )->alias(
			function ( $user_id, $key ) {
				return $this->meta[ $key ] ?? '';
			}
		);
		Functions\when( 'update_user_meta' )->alias(
			function ( $user_id, $key, $value ) {
				$this->meta[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_user_meta' )->alias(
			function ( $user_id, $key ) {
				unset( $this->meta[ $key ] );
				return true;
			}
		);
		Functions\when( 'get_users' )->justReturn( array() );
		Functions\when( 'get_userdata' )->justReturn( false );

		// The singleton keeps the last save (for the form shown after it).
		$profile = Member_Profile::instance();
		foreach ( array( 'user_id' => 0, 'posted' => array(), 'pending' => array(), 'errors' => array(), 'approve' => false ) as $name => $value ) {
			$property = new \ReflectionProperty( $profile, $name );
			$property->setValue( $profile, $value );
		}
	}

	protected function tearDown(): void {
		$_POST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Save as WordPress does: the update hook, then the errors hook.
	 *
	 * @param array<string,mixed> $post   Form fields.
	 * @param \WP_Error|null      $errors WordPress's own errors.
	 * @return \WP_Error
	 */
	private function save( array $post, ?\WP_Error $errors = null ): \WP_Error {
		$_POST  = $post;
		$errors = $errors ?? new \WP_Error();
		Member_Profile::instance()->save_member_meta_fields( 9 );
		Member_Profile::instance()->report_errors( $errors );
		return $errors;
	}

	/**
	 * The stores rows of the form.
	 *
	 * @param array<int,array{0:string|int,1:string}> $rows ID and text per position.
	 * @return array<string,mixed>
	 */
	private static function stores( array $rows ): array {
		$out = array();
		foreach ( $rows as $index => $row ) {
			$out[ $index ] = array(
				'id'    => (string) $row[0],
				'title' => $row[1],
			);
		}
		return array( 'aa_member_resellers' => $out );
	}

	public function test_fieldsets_follow_the_woocommerce_mold(): void {
		$fieldsets = Member_Profile::instance()->get_member_meta_fields( 9 );

		$this->assertSame( array( 'authorship', 'document', 'address', 'resellers' ), array_keys( $fieldsets ) );
		$this->assertSame( array( 'company', 'phone', 'professional_registration', 'primary_focus' ), array_keys( $fieldsets['authorship']['fields'] ) );
		$this->assertArrayHasKey( 'BR', $fieldsets['address']['fields']['country']['options'] );
		$this->assertSame( 'select', $fieldsets['address']['fields']['state']['type'], 'Brazil (the default) picks a UF.' );
		$this->assertSame( array( 'resellers' ), array_keys( $fieldsets['resellers']['fields'] ), 'One row for the stores.' );
		$this->assertSame( 'resellers', $fieldsets['resellers']['fields']['resellers']['type'] );
	}

	public function test_the_country_is_brazil_and_another_one_is_refused(): void {
		$country = Member_Profile::instance()->get_member_meta_fields( 9 )['address']['fields']['country'];
		$this->assertTrue( $country['disabled'] );
		$this->assertArrayHasKey( 'AR', $country['options'], 'Every country is listed.' );

		$errors = $this->save( array( 'aa_member_country' => 'AR' ) );

		$this->assertSame( array( 'aa_invalid_country' ), $errors->get_error_codes() );
		$this->assertArrayNotHasKey( 'billing_country', $this->meta );

		$this->save( array( 'aa_member_company' => 'New studio' ) );
		$this->assertSame( 'BR', $this->meta['billing_country'] );
	}

	public function test_fields_follow_the_form_order(): void {
		$fieldsets = Member_Profile::instance()->get_member_meta_fields( 9 );

		$this->assertSame( 'Atelier: Authorship', $fieldsets['authorship']['title'] );
		$this->assertSame( array( 'profile_type', 'br_revenue_id' ), array_keys( $fieldsets['document']['fields'] ) );
		$this->assertSame(
			array( 'country', 'address_street', 'address_number', 'address_2', 'neighborhood', 'landmark', 'state', 'city', 'postal' ),
			array_keys( $fieldsets['address']['fields'] )
		);
		$this->assertTrue( $fieldsets['document']['fields']['profile_type']['disabled'] );
	}

	public function test_with_woocommerce_a_customer_keeps_its_billing_fields_there(): void {
		Filters\expectApplied( 'axellcore_atelierclub_woocommerce_active' )->andReturn( true );
		Functions\when( 'get_userdata' )->justReturn( (object) array( 'roles' => array( 'customer' ) ) );

		$fieldsets = Member_Profile::instance()->get_member_meta_fields( 9 );

		$this->assertSame( array( 'professional_registration', 'primary_focus' ), array_keys( $fieldsets['authorship']['fields'] ) );
		$this->assertSame( array( 'address_number', 'neighborhood', 'landmark' ), array_keys( $fieldsets['address']['fields'] ) );
	}

	public function test_a_clean_save_writes_after_wordpress_checked_its_fields(): void {
		$_POST = array(
			'aa_member_company'      => ' New studio ',
			'aa_member_profile_type' => 'legal_entity',
			'aa_member_phone'        => '(11) 91234-5678',
			'aa_member_postal'       => '01001-000',
		);
		Member_Profile::instance()->save_member_meta_fields( 9 );
		$this->assertSame( 'Old studio', $this->meta['billing_company'], 'Nothing is written before the errors hook.' );

		Member_Profile::instance()->report_errors( new \WP_Error() );

		$this->assertSame( 'New studio', $this->meta['billing_company'] );
		$this->assertSame( '+5511912345678', $this->meta['billing_phone'] );
		$this->assertSame( '01001000', $this->meta['billing_postcode'] );
		$this->assertArrayNotHasKey( 'profile_type', $this->meta, 'The type follows the document, never stored.' );
	}

	public function test_one_wrong_field_saves_nothing(): void {
		$errors = $this->save(
			array(
				'aa_member_company'       => 'New studio',
				'aa_member_br_revenue_id' => '111.111.111-11',
			)
		);

		$this->assertSame( array( 'aa_invalid_cpf' ), $errors->get_error_codes() );
		$this->assertSame( 'Old studio', $this->meta['billing_company'] );
		$this->assertSame( '52998224725', $this->meta['billing_cpf'] );
	}

	public function test_an_option_outside_the_list_is_an_error(): void {
		$errors = $this->save( array( 'aa_member_primary_focus' => 'not-an-option' ) );

		$this->assertSame( array( 'aa_invalid_option' ), $errors->get_error_codes() );
		$this->assertArrayNotHasKey( 'primary_focus', $this->meta );
	}

	public function test_phone_and_cep_must_be_complete(): void {
		$errors = $this->save(
			array(
				'aa_member_phone'  => '(11) 1234',
				'aa_member_postal' => '0100',
			)
		);

		$this->assertSame( array( 'aa_invalid_phone', 'aa_invalid_postal' ), $errors->get_error_codes() );
	}

	public function test_an_error_of_wordpress_saves_nothing_either(): void {
		$this->save( array( 'aa_member_company' => 'New studio' ), new \WP_Error( 'empty_email', 'Please enter an email address.' ) );

		$this->assertSame( 'Old studio', $this->meta['billing_company'] );
	}

	public function test_a_valid_document_is_saved_normalized(): void {
		$this->save( array( 'aa_member_br_revenue_id' => '168.995.350-09' ) );
		$this->assertSame( '16899535009', $this->meta['billing_cpf'] );

		// An alphanumeric CNPJ moves the document to billing_cnpj.
		$this->save( array( 'aa_member_br_revenue_id' => '12.abc.345/01de-35' ) );
		$this->assertSame( '12ABC34501DE35', $this->meta['billing_cnpj'] );
		$this->assertArrayNotHasKey( 'billing_cpf', $this->meta );
	}

	public function test_without_permission_nothing_is_saved_or_shown(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->save( array( 'aa_member_company' => 'New studio' ) );
		$this->assertSame( 'Old studio', $this->meta['billing_company'] );

		$user     = new \WP_User();
		$user->ID = 9;
		ob_start();
		Member_Profile::instance()->add_member_meta_fields( $user );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_stores_move_up_and_keep_what_the_member_had(): void {
		$errors = $this->save(
			self::stores(
				array(
					array( '', '' ),
					array( 50, 'whatever the browser sent' ),
					array( 77, 'Loja pendente - RS Caxias do Sul' ),
					array( 50, 'Nova · RS Caxias do Sul' ),
				)
			)
		);

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( '50,77', $this->meta['reseller_ids'], 'IDs only, in order, once each; a pending one the member had stays.' );
		$this->assertCount( 0, preg_grep( '/^reseller\d/', array_keys( $this->meta ) ), 'No per-position keys.' );
	}

	public function test_an_empty_store_between_others_leaves_no_gap(): void {
		$errors = $this->save(
			self::stores(
				array(
					array( 42, 'Loja A · RS Caxias do Sul' ),
					array( '', '' ),
					array( '', '' ),
					array( 50, 'Nova · RS Caxias do Sul' ),
				)
			)
		);

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( '42,50', $this->meta['reseller_ids'] );
	}

	public function test_a_store_must_be_a_registered_reseller(): void {
		foreach ( array( array( 99, 'Sobre' ), array( '', 'Typed by hand' ) ) as $row ) {
			$errors = $this->save( self::stores( array( $row ) ) + array( 'aa_member_company' => 'New studio' ) );

			$this->assertSame( array( 'aa_invalid_reseller' ), $errors->get_error_codes() );
			$this->assertSame( '42,77', $this->meta['reseller_ids'] );
			$this->assertSame( 'Old studio', $this->meta['billing_company'] );
		}
	}

	public function test_stores_are_shown_with_their_revendas_text(): void {
		$user     = new \WP_User();
		$user->ID = 9;
		ob_start();
		Member_Profile::instance()->add_member_meta_fields( $user );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'value="Loja A · RS Caxias do Sul"', $html );
		$this->assertStringContainsString( 'value="Loja pendente · RS Caxias do Sul"', $html );
	}

	public function test_main_practice_is_stored_by_its_label(): void {
		$this->save( array( 'aa_member_primary_focus' => 'Design de interiores' ) );

		$this->assertSame( 'Design de interiores', $this->meta['primary_focus'] );
	}

	public function test_after_an_error_the_form_shows_what_was_sent_and_marks_the_field(): void {
		$this->save(
			array(
				'aa_member_company'       => 'New studio',
				'aa_member_br_revenue_id' => '111.111.111-11',
			)
		);
		$user     = new \WP_User();
		$user->ID = 9;
		ob_start();
		Member_Profile::instance()->add_member_meta_fields( $user );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'value="New studio"', $html );
		$this->assertStringContainsString( 'value="111.111.111-11"', $html );
		$this->assertStringContainsString( '<tr class="form-required form-invalid"><th><label for="aa_member_br_revenue_id" id="aa_member_br_revenue_id-label">', $html );
		$this->assertStringContainsString( 'aria-describedby="aa_member_br_revenue_id-error"', $html );
		$this->assertStringContainsString( 'id="aa_member_br_revenue_id-error">Invalid CPF.</p>', $html );
		$this->assertMatchesRegularExpression( '/<select name="aa_member_profile_type" id="aa_member_profile_type"[^>]* disabled[ >][^>]*>\\s*<option value="" selected="selected">/', $html, 'An invalid number has no type.' );
		$this->assertMatchesRegularExpression( '/<select name="aa_member_country"[^>]* disabled[ >]/', $html );
		$this->assertMatchesRegularExpression( '/<button type="button" class="aa-member-select__toggle"[^>]*id="aa_member_country-toggle" disabled/', $html );
	}

	public function test_stored_values_are_shown_with_their_masks(): void {
		$this->meta['billing_phone']    = '+5511987654321';
		$this->meta['billing_postcode'] = '01001000';
		$user                           = new \WP_User();
		$user->ID                       = 9;
		ob_start();
		Member_Profile::instance()->add_member_meta_fields( $user );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'value="(11) 98765-4321"', $html );
		$this->assertStringContainsString( 'value="01001-000"', $html );
		$this->assertStringContainsString( 'value="529.982.247-25"', $html );
		$this->assertStringContainsString( '<option value="individual" selected="selected">', $html );
	}

	/**
	 * The profile's HTML for a user with these roles.
	 *
	 * @param string[] $roles Roles.
	 */
	private function profile_html( array $roles ): string {
		Functions\when( 'admin_url' )->alias( static fn( $p ) => 'https://example.com/wp-admin/' . $p );
		Functions\when( 'add_query_arg' )->alias( static fn( $args, $url ) => $url . '?' . http_build_query( $args ) );
		Functions\when( 'wp_nonce_url' )->alias( static fn( $url, $action ) => $url . '&_wpnonce=' . $action );
		$user        = new \WP_User();
		$user->ID    = 9;
		$user->roles = $roles;
		ob_start();
		Member_Profile::instance()->add_member_meta_fields( $user );
		return (string) ob_get_clean();
	}

	public function test_status_approves_a_pending_member(): void {
		$html = $this->profile_html( array( 'member_pending' ) );

		$this->assertStringContainsString( '<h2>Atelier: Status</h2>', $html );
		$this->assertStringContainsString( '<input type="hidden" name="aa_member_approve" id="aa-member-approve" value="0"><button type="button" class="button" data-wp-interactive="axell/member-fields" data-wp-on--click="actions.approve">Approve member</button>', $html );
		$this->assertStringContainsString( 'This application is pending approval.</p>', $html );
	}

	public function test_status_of_an_approved_member_is_a_disabled_button(): void {
		$html = $this->profile_html( array( 'member' ) );

		$this->assertStringContainsString( '<button type="button" class="button" disabled>Member approved</button>', $html );
		$this->assertStringNotContainsString( 'aa-approve', $html );
	}

	public function test_no_status_for_other_users(): void {
		$this->assertStringNotContainsString( 'Atelier: Status', $this->profile_html( array( 'administrator' ) ) );
	}

	/**
	 * Save the profile as WordPress does, with the user data object.
	 *
	 * @param array<string,mixed> $post  Form fields.
	 * @param string[]            $roles The user's roles.
	 * @return \stdClass The user data WordPress would save.
	 */
	private function save_as_profile( array $post, array $roles ): \stdClass {
		$user        = new \WP_User();
		$user->ID    = 9;
		$user->roles = $roles;
		Functions\when( 'get_userdata' )->justReturn( $user );
		$_POST = $post;
		Member_Profile::instance()->save_member_meta_fields( 9 );
		$data       = new \stdClass();
		$data->ID   = 9;
		$data->role = $roles[0];
		Member_Profile::instance()->report_errors( new \WP_Error(), true, $data );
		return $data;
	}

	public function test_approve_member_saves_the_profile_and_approves(): void {
		$data = $this->save_as_profile( array( 'aa_member_approve' => '1', 'aa_member_company' => 'New studio' ), array( 'member_pending' ) );

		$this->assertSame( 'New studio', $this->meta['billing_company'] );
		$this->assertSame( 'member', $data->role, 'WordPress saves the member role.' );
		$this->assertTrue( has_action( 'profile_update' ) !== false, 'The approval event fires once saved.' );
	}

	public function test_no_approval_with_an_error_without_the_flag_or_permission(): void {
		$data = $this->save_as_profile( array( 'aa_member_approve' => '1', 'aa_member_br_revenue_id' => '111.111.111-11' ), array( 'member_pending' ) );
		$this->assertSame( 'member_pending', $data->role, 'An error: nothing saved, still pending.' );

		$data = $this->save_as_profile( array( 'aa_member_approve' => '0', 'aa_member_company' => 'X' ), array( 'member_pending' ) );
		$this->assertSame( 'member_pending', $data->role, 'Update User alone does not approve.' );

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		$data = $this->save_as_profile( array( 'aa_member_approve' => '1' ), array( 'member_pending' ) );
		$this->assertSame( 'member_pending', $data->role, 'Who cannot approve does not.' );
	}
}
