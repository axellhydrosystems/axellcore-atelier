<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Members;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class MembersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
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

	public function test_missing_required_field_returns_400(): void {
		Functions\when( 'get_transient' )->justReturn( 0 );
		Functions\when( 'set_transient' )->justReturn( true );

		$result = Members::instance()->submit( array(), '203.0.113.8' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'aa_missing_field', $result->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $result->get_error_data() );
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
		$this->assertSame( array( 'status' => 409 ), $result->get_error_data() );
	}

	public function test_registered_document_is_rejected_with_409(): void {
		$this->stub_validation();
		Functions\when( 'email_exists' )->justReturn( false );
		Functions\expect( 'get_users' )->once()->andReturnUsing(
			function ( $args ) {
				$this->assertSame( '52998224725', $args['meta_value'] );
				return array( 3 );
			}
		);

		$result = Members::instance()->create_from_params( $this->valid_params() );

		$this->assertSame( 'aa_document_exists', $result->get_error_code() );
		$this->assertSame( array( 'status' => 409 ), $result->get_error_data() );
	}

	public function test_application_creates_a_pending_member_user_with_meta(): void {
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

		$result = Members::instance()->create_from_params( $this->valid_params() );

		$this->assertSame( array( 'success' => true, 'id' => 42 ), $result );
		$this->assertSame( 'ana', $user['user_login'] );
		$this->assertSame( 'member_pending', $user['role'] );
		$this->assertSame( 'Campinas', $meta['city'] );
		$this->assertSame( 'SP', $meta['state'] );
		$this->assertSame( '52998224725', $meta['br_revenue_id'] );
		$this->assertArrayNotHasKey( 'aa_city', $meta );
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
