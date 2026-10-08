<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Members;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class MembersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		// Settings saved: none (reCAPTCHA on, but without keys it does nothing).
		Functions\when( 'get_option' )->justReturn( array() );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_honeypot_filled_reports_success_without_touching_the_rate_limit(): void {
		Functions\expect( 'get_transient' )->never();
		Functions\expect( 'set_transient' )->never();

		$result = Members::instance()->submit( array( Members::HONEYPOT_FIELD => 'spam' ), '203.0.113.7' );

		$this->assertSame( array( 'success' => true, 'id' => 0 ), $result );
	}

	public function test_recaptcha_refuses_a_submission_without_its_token(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'recaptcha_enabled'       => true,
				'recaptcha_v3_site_key'   => 'site',
				'recaptcha_v3_secret_key' => 'secret',
			)
		);
		Functions\when( 'home_url' )->justReturn( 'https://axell.com.br/' );
		Functions\expect( 'set_transient' )->never();
		Functions\expect( 'wp_insert_user' )->never();

		$result = Members::instance()->submit( array( 'fullname' => 'Ana' ), '203.0.113.6' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'aa_recaptcha_failed', $result->get_error_code() );
	}

	public function test_missing_required_field_returns_400(): void {
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = Members::instance()->submit( array(), '203.0.113.8' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'aa_missing_field', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400, 'field' => 'fullname' ), $result->get_error_data() );
	}

	public function test_invalid_email_returns_400(): void {
		Functions\when( 'sanitize_email' )->justReturn( '' );
		Functions\when( 'is_email' )->justReturn( false );

		$params = array_fill_keys( Members::REQUIRED_FIELDS, 'x' );
		$params['email'] = 'not-an-email';

		$result = Members::instance()->create_from_params( $params );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'aa_invalid_email', $result->get_error_code() );
	}

	public function test_sixth_submission_within_the_window_is_rate_limited(): void {
		// Each submission counts, even if it fails validation later.
		$count = 0;
		Functions\when( 'get_transient' )->alias(
			static function () use ( &$count ) {
				return $count;
			}
		);
		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value ) use ( &$count ) {
				$count = $value;
				return true;
			}
		);

		for ( $i = 0; $i < Members::RATE_LIMIT; $i++ ) {
			$result = Members::instance()->submit( array(), '203.0.113.9' );
			$this->assertSame( 'aa_missing_field', $result->get_error_code() );
		}

		$limited = Members::instance()->submit( array(), '203.0.113.9' );

		$this->assertSame( 'aa_rate_limited', $limited->get_error_code() );
		$this->assertSame( array( 'status' => 429 ), $limited->get_error_data() );
	}

	public function test_missing_field_is_reported_by_its_english_form_name(): void {
		$result = Members::instance()->create_from_params( array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'fullname', $result->get_error_message() );
	}

	/**
	 * A valid application (State/city that exist in the bundled data).
	 *
	 * @return array<string,string>
	 */
	private function valid_params(): array {
		$params                  = array_fill_keys( Members::REQUIRED_FIELDS, 'x' );
		$params['email']         = 'ana@escritorio.com.br';
		$params['state']         = 'SP';
		$params['city']          = 'Campinas';
		$params['br_revenue_id'] = '529.982.247-25';
		$params['phone']         = '(11) 98765-4321';
		$params['postal']        = '01001-000';
		return $params;
	}

	private function stub_validation(): void {
		Functions\when( 'sanitize_email' )->returnArg( 1 );
		Functions\when( 'is_email' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
	}

	public function test_city_not_in_the_state_is_rejected(): void {
		$this->stub_validation();
		$params         = $this->valid_params();
		$params['city'] = 'Curitiba';

		$result = Members::instance()->create_from_params( $params );

		$this->assertSame( 'aa_invalid_location', $result->get_error_code() );
	}

	public function test_registered_email_is_rejected_with_409(): void {
		$this->stub_validation();
		Functions\when( 'email_exists' )->justReturn( 7 );

		$result = Members::instance()->create_from_params( $this->valid_params() );

		$this->assertSame( 'aa_email_exists', $result->get_error_code() );
		$this->assertSame( array( 'status' => 409, 'field' => 'email' ), $result->get_error_data() );
	}

	public function test_brazilian_phone_must_be_a_mobile_or_landline_with_area_code(): void {
		$this->stub_validation();
		foreach ( array( '(11) 8765-4321' => true, '(11) 3456-7890' => false, '(11) 98765-432' => true, '(11) 98765-4321' => false ) as $phone => $rejected ) {
			$params          = $this->valid_params();
			$params['phone'] = $phone;
			Functions\when( 'email_exists' )->justReturn( 7 );

			$result = Members::instance()->create_from_params( $params );

			$this->assertSame( $rejected ? 'aa_invalid_phone' : 'aa_email_exists', $result->get_error_code(), $phone );
		}
	}

	public function test_brazilian_cep_must_have_8_digits(): void {
		$this->stub_validation();
		$params           = $this->valid_params();
		$params['postal'] = '01001-00';

		$result = Members::instance()->create_from_params( $params );

		$this->assertSame( 'aa_invalid_postal', $result->get_error_code() );
		$this->assertSame( 'postal', $result->get_error_data()['field'] );
	}

	public function test_reseller_must_be_a_published_revenda(): void {
		$this->stub_validation();
		Functions\when( 'absint' )->alias( 'intval' );
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		$params              = $this->valid_params();
		$params['reseller2'] = '2625';

		$result = Members::instance()->create_from_params( $params );

		$this->assertSame( 'aa_invalid_reseller', $result->get_error_code() );
		$this->assertSame( 'reseller2_title', $result->get_error_data()['field'] );
	}

	public function test_main_practice_by_label_and_stores_as_revenda_ids(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$this->stub_validation();
		Functions\when( 'email_exists' )->justReturn( false );
		Functions\when( 'get_users' )->justReturn( array() );
		Functions\when( 'sanitize_user' )->returnArg( 1 );
		Functions\when( 'username_exists' )->justReturn( false );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'wp_generate_password' )->justReturn( 'secret' );
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'get_post_type' )->justReturn( 'revendas' );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'wp_insert_user' )->justReturn( 42 );
		Functions\when( 'delete_user_meta' )->justReturn( true );
		$meta = array();
		Functions\when( 'update_user_meta' )->alias(
			static function ( $id, $key, $value ) use ( &$meta ) {
				$meta[ $key ] = $value;
				return true;
			}
		);
		// The store given by text becomes a pending revenda (Reseller_Store).
		Filters\expectApplied( 'axellcore_atelierclub_reseller_text' )->once()->with( 0, 'Loja Nova - SC Joinville' )->andReturn( 900 );
		$params                    = $this->valid_params();
		$params['primary_focus']   = 'interior_design';
		// Store 2 left empty: the stores move up on the server.
		$params['reseller1_title'] = 'Loja Nova - SC Joinville';
		$params['reseller3']       = '2625';
		$params['reseller3_title'] = 'A Casa · RS Caxias do Sul';

		Members::instance()->create_from_params( $params );

		$this->assertSame( 'Design de interiores', $meta['primary_focus'], 'Stored by its label.' );
		$this->assertSame( '900,2625', $meta['reseller_ids'], 'Store 3 after store 1: no gap.' );
		$this->assertCount( 0, preg_grep( '/^reseller\d/', array_keys( $meta ) ), 'No per-position keys or texts.' );
	}

	public function test_an_invalid_portfolio_address_is_refused(): void {
		$this->stub_validation();
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		$params        = $this->valid_params();
		$params['url'] = 'meu portfolio';

		$result = Members::instance()->create_from_params( $params );

		$this->assertSame( 'aa_invalid_url', $result->get_error_code() );
		$this->assertSame( 'url', $result->get_error_data()['field'] );
	}

	public function test_a_store_text_that_cannot_become_a_revenda_is_refused(): void {
		$this->stub_validation();
		Functions\when( 'absint' )->alias( 'intval' );
		$params                    = $this->valid_params();
		$params['reseller2_title'] = 'Uma loja qualquer';

		$result = Members::instance()->create_from_params( $params );

		$this->assertSame( 'aa_invalid_store', $result->get_error_code() );
		$this->assertSame( 'reseller2_title', $result->get_error_data()['field'] );
	}

	public function test_invalid_document_is_rejected_with_400(): void {
		$this->stub_validation();
		Functions\when( 'email_exists' )->justReturn( false );
		$params                  = $this->valid_params();
		$params['br_revenue_id'] = '529.982.247-24';

		$result = Members::instance()->create_from_params( $params );

		$this->assertSame( 'aa_invalid_cpf', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_document_of_the_other_profile_type_is_rejected(): void {
		$this->stub_validation();
		Functions\when( 'email_exists' )->justReturn( false );
		$params                 = $this->valid_params();
		$params['profile_type'] = 'legal_entity';

		$result = Members::instance()->create_from_params( $params );

		$this->assertSame( 'aa_invalid_cnpj', $result->get_error_code() );
	}

	public function test_registered_document_is_rejected_with_409(): void {
		$this->stub_validation();
		Functions\when( 'email_exists' )->justReturn( false );
		Functions\expect( 'get_users' )->once()->andReturnUsing(
			function ( $args ) {
				$this->assertSame( array( 'billing_cpf', 'billing_cnpj' ), array_column( array_slice( $args['meta_query'], 1 ), 'key' ) );
				$this->assertSame( '52998224725', $args['meta_query'][0 + 1]['value'] );
				return array( 3 );
			}
		);

		$result = Members::instance()->create_from_params( $this->valid_params() );

		$this->assertSame( 'aa_cpf_exists', $result->get_error_code() );
		$this->assertSame( array( 'status' => 409, 'field' => 'br_revenue_id' ), $result->get_error_data() );
	}

	public function test_application_creates_a_pending_member_user_with_meta(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$this->stub_validation();
		Functions\when( 'email_exists' )->justReturn( false );
		Functions\when( 'get_users' )->justReturn( array() );
		Functions\when( 'sanitize_user' )->returnArg( 1 );
		Functions\when( 'username_exists' )->justReturn( false );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'wp_generate_password' )->justReturn( 'secret' );
		Functions\when( 'absint' )->alias( 'intval' );
		$user = array();
		Functions\expect( 'wp_insert_user' )->once()->andReturnUsing(
			static function ( $data ) use ( &$user ) {
				$user = $data;
				return 42;
			}
		);
		$meta = array();
		Functions\when( 'update_user_meta' )->alias(
			static function ( $id, $key, $value ) use ( &$meta ) {
				$meta[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_user_meta' )->justReturn( true );
		$params             = $this->valid_params();
		$params['fullname'] = 'Ana Maria  Souza';
		$params['company']  = 'Estúdio Ana';

		$result = Members::instance()->create_from_params( $params );

		$this->assertSame( array( 'success' => true, 'id' => 42 ), $result );
		$this->assertSame( 'ana', $user['user_login'] );
		$this->assertSame( 'member_pending', $user['role'] );
		$this->assertSame( 'Ana Maria  Souza', $user['display_name'] );
		$this->assertSame( array( 'Ana', 'Maria  Souza' ), array( $user['first_name'], $user['last_name'] ) );
		$this->assertSame( array( 'Ana', 'Maria  Souza' ), array( $meta['billing_first_name'], $meta['billing_last_name'] ) );
		$this->assertSame( 'ana@escritorio.com.br', $meta['billing_email'] );
		$this->assertSame( 'Estúdio Ana', $meta['billing_company'] );
		$this->assertSame( 'Campinas', $meta['billing_city'] );
		$this->assertSame( 'SP', $meta['billing_state'] );
		$this->assertSame( 'BR', $meta['billing_country'] );
		$this->assertSame( '+5511987654321', $meta['billing_phone'], 'Digits with +55.' );
		$this->assertSame( '01001000', $meta['billing_postcode'], 'Digits only.' );
		$this->assertSame( '52998224725', $meta['billing_cpf'] );
		$this->assertArrayNotHasKey( 'billing_cnpj', $meta );
		$this->assertArrayNotHasKey( 'profile_type', $meta, 'The type follows the document.' );
		$this->assertArrayNotHasKey( 'company', $meta );
	}

	public function test_application_creates_an_approved_member_when_pending_is_off(): void {
		Functions\when( 'get_option' )->justReturn( array( 'pending_on_create' => false ) );
		$this->stub_validation();
		Functions\when( 'email_exists' )->justReturn( false );
		Functions\when( 'get_users' )->justReturn( array() );
		Functions\when( 'sanitize_user' )->returnArg( 1 );
		Functions\when( 'username_exists' )->justReturn( false );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'wp_generate_password' )->justReturn( 'secret' );
		Functions\when( 'absint' )->alias( 'intval' );
		Functions\when( 'update_user_meta' )->justReturn( true );
		Functions\when( 'delete_user_meta' )->justReturn( true );
		$user = array();
		Functions\expect( 'wp_insert_user' )->once()->andReturnUsing(
			static function ( $data ) use ( &$user ) {
				$user = $data;
				return 43;
			}
		);

		Members::instance()->create_from_params( $this->valid_params() );

		$this->assertSame( 'member', $user['role'] );
	}

	public function test_username_is_the_email_prefix(): void {
		Functions\when( 'sanitize_user' )->returnArg( 1 );
		Functions\when( 'username_exists' )->justReturn( false );

		$this->assertSame( 'ana.souza', Members::username_for( 'Ana.Souza@escritorio.com.br' ) );
	}

	public function test_generic_email_prefix_uses_the_domain(): void {
		Functions\when( 'sanitize_user' )->returnArg( 1 );
		Functions\when( 'username_exists' )->justReturn( false );

		$this->assertSame( 'escritorio.com.br', Members::username_for( 'contact@escritorio.com.br' ) );
	}

	public function test_taken_username_gets_a_numeric_suffix(): void {
		Functions\when( 'sanitize_user' )->returnArg( 1 );
		Functions\when( 'username_exists' )->alias(
			static function ( $login ) {
				return 'ana' === $login ? 1 : false;
			}
		);
		Functions\when( 'wp_rand' )->justReturn( 7 );
		Functions\when( 'zeroise' )->alias(
			static function ( $n, $t ) {
				return str_pad( (string) $n, $t, '0', STR_PAD_LEFT );
			}
		);

		$this->assertSame( 'ana-0007', Members::username_for( 'ana@escritorio.com.br' ) );
	}

	public function test_state_and_city_any_case_are_stored_canonical(): void {
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );

		$this->assertSame( array( 'SP', 'Campinas' ), Members::location( 'BR', 'sp', 'campinas' ) );
	}

	public function test_state_of_another_country_is_a_name_of_two_chars_or_more(): void {
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );

		$this->assertSame( array( 'Buenos Aires', 'La Plata' ), Members::location( 'AR', 'Buenos Aires', 'La Plata' ) );
		$this->assertSame( 'aa_invalid_location', Members::location( 'AR', 'B', 'La Plata' )->get_error_code() );
	}
}
