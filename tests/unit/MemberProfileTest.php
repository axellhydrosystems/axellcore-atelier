<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Member_Profile;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class MemberProfileTest extends TestCase {

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
			'company'          => 'Old studio',
			'reseller1'        => 42,
			'reseller1_title'  => 'Loja A',
			'reseller2'        => 43,
			'reseller2_title'  => 'Loja B',
			'br_revenue_id'    => '52998224725',
			'profile_type'     => 'individual',
		);
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_user_locale' )->justReturn( 'en_US' );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
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
	}

	protected function tearDown(): void {
		$_POST = array();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_fieldsets_follow_the_woocommerce_mold(): void {
		$fieldsets = Member_Profile::instance()->get_member_meta_fields( 9 );

		$this->assertSame( array( 'authorship', 'document', 'address', 'resellers' ), array_keys( $fieldsets ) );
		$this->assertSame( array( 'company', 'phone', 'professional_registration', 'primary_focus' ), array_keys( $fieldsets['authorship']['fields'] ) );
		$this->assertSame( 'select', $fieldsets['address']['fields']['country']['type'] );
		$this->assertArrayHasKey( 'BR', $fieldsets['address']['fields']['country']['options'] );
		$this->assertSame( 'select', $fieldsets['address']['fields']['state']['type'], 'Brazil (the default) picks a UF.' );
		$this->assertCount( 5, $fieldsets['resellers']['fields'] );
	}

	public function test_state_is_text_outside_brazil(): void {
		$this->meta['country'] = 'us';

		$state = Member_Profile::instance()->get_member_meta_fields( 9 )['address']['fields']['state'];

		$this->assertArrayNotHasKey( 'type', $state );
	}

	public function test_saves_text_and_only_known_select_options(): void {
		$_POST = array(
			'aa_member_company'       => ' New studio ',
			'aa_member_primary_focus' => 'not-an-option',
			'aa_member_profile_type'  => 'legal_entity',
		);

		Member_Profile::instance()->save_member_meta_fields( 9 );

		$this->assertSame( 'New studio', $this->meta['company'] );
		$this->assertArrayNotHasKey( 'primary_focus', $this->meta );
		$this->assertSame( 'legal_entity', $this->meta['profile_type'] );
	}

	public function test_without_permission_nothing_is_saved_or_shown(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$_POST = array( 'aa_member_company' => 'New studio' );

		Member_Profile::instance()->save_member_meta_fields( 9 );
		$user     = new \WP_User();
		$user->ID = 9;
		ob_start();
		Member_Profile::instance()->add_member_meta_fields( $user );

		$this->assertSame( '', ob_get_clean() );
		$this->assertSame( 'Old studio', $this->meta['company'] );
	}

	public function test_an_invalid_document_keeps_the_old_one_and_is_reported(): void {
		$_POST = array(
			'aa_member_company'       => 'New studio',
			'aa_member_br_revenue_id' => '111.111.111-11',
		);

		Member_Profile::instance()->save_member_meta_fields( 9 );
		$errors = new \WP_Error();
		Member_Profile::instance()->report_errors( $errors );

		$this->assertSame( '52998224725', $this->meta['br_revenue_id'] );
		$this->assertSame( 'New studio', $this->meta['company'] );
		$this->assertSame( array( 'aa_invalid_cpf' ), $errors->get_error_codes() );
	}

	public function test_a_valid_document_is_saved_normalized(): void {
		$_POST = array( 'aa_member_br_revenue_id' => '168.995.350-09' );

		Member_Profile::instance()->save_member_meta_fields( 9 );

		$this->assertSame( '16899535009', $this->meta['br_revenue_id'] );
	}

	public function test_a_changed_store_text_unlinks_the_reseller_and_empty_clears_it(): void {
		$_POST = array(
			'aa_member_reseller1_title' => 'Loja informada',
			'aa_member_reseller2_title' => '',
		);

		Member_Profile::instance()->save_member_meta_fields( 9 );

		$this->assertSame( 0, $this->meta['reseller1'] );
		$this->assertSame( 'Loja informada', $this->meta['reseller1_title'] );
		$this->assertArrayNotHasKey( 'reseller2', $this->meta );
		$this->assertArrayNotHasKey( 'reseller2_title', $this->meta );
	}

	public function test_an_unchanged_store_text_keeps_the_reseller(): void {
		$_POST = array( 'aa_member_reseller1_title' => 'Loja A' );

		Member_Profile::instance()->save_member_meta_fields( 9 );

		$this->assertSame( 42, $this->meta['reseller1'] );
	}
}
