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
}
