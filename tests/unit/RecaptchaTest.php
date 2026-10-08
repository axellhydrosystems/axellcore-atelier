<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Recaptcha;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class RecaptchaTest extends TestCase {

	/**
	 * The request sent to Google, if any.
	 *
	 * @var array|null
	 */
	private $sent = null;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'home_url' )->justReturn( 'https://axell.com.br/' );
		$this->sent = null;
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function settings( array $values ): void {
		Functions\when( 'get_option' )->justReturn(
			$values + array(
				'recaptcha_enabled'       => true,
				'recaptcha_v3_site_key'   => 'site-v3',
				'recaptcha_v3_secret_key' => 'secret-v3',
				'recaptcha_site_key'      => 'site-v2',
				'recaptcha_secret_key'    => 'secret-v2',
			)
		);
	}

	private function google( ?array $result, int $code = 200 ): void {
		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( $result, $code ) {
				$this->sent = array( $url, $args['body'] );
				return array(
					'code' => $code,
					'body' => null === $result ? 'not json' : json_encode( $result ),
				);
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $r ) => $r['code'] );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $r ) => $r['body'] );
	}

	public function test_local_addresses(): void {
		foreach ( array( 'http://localhost:8884', 'http://localhost', 'https://localhost/', 'https://127.0.0.1', 'http://127.0.0.1:8080/site/' ) as $url ) {
			$this->assertTrue( Recaptcha::is_local( $url ), $url );
		}
		foreach ( array( 'https://axell.com.br', 'http://localhost.com.br', 'http://127.0.0.10', 'ftp://localhost', 'http://my-localhost' ) as $url ) {
			$this->assertFalse( Recaptcha::is_local( $url ), $url );
		}
	}

	public function test_active_needs_on_and_the_version_keys(): void {
		$this->settings( array() );
		$this->assertTrue( Recaptcha::active() );
		$this->assertSame( 'v3', Recaptcha::version(), 'v3 by default.' );
		$this->assertSame( 'site-v3', Recaptcha::site_key() );

		$this->settings( array( 'recaptcha_enabled' => false ) );
		$this->assertFalse( Recaptcha::active() );

		$this->settings( array( 'recaptcha_v3_secret_key' => '' ) );
		$this->assertFalse( Recaptcha::active(), 'v3 without its secret key.' );

		$this->settings( array( 'recaptcha_version' => 'v2', 'recaptcha_v3_secret_key' => '' ) );
		$this->assertTrue( Recaptcha::active(), 'v2 uses its own keys.' );
		$this->assertSame( 'secret-v2', Recaptcha::secret_key() );
	}

	public function test_local_site_skips_only_when_set(): void {
		Functions\when( 'home_url' )->justReturn( 'http://localhost:8884/' );

		$this->settings( array() );
		$this->assertFalse( Recaptcha::active(), 'Skipped on a local address by default.' );

		$this->settings( array( 'recaptcha_skip_local' => false ) );
		$this->assertTrue( Recaptcha::active() );
	}

	public function test_no_token_is_refused_without_asking_google(): void {
		$this->settings( array() );
		Functions\expect( 'wp_remote_post' )->never();

		$error = Recaptcha::verify( array( 'email' => 'a@b.c' ), '1.2.3.4' );
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'aa_recaptcha_failed', $error->get_error_code() );
	}

	public function test_v3_needs_the_action_and_the_score(): void {
		$this->settings( array( 'recaptcha_v3_threshold' => 0.7 ) );
		$params = array( 'g-recaptcha-response' => 'token' );

		$this->google( array( 'success' => true, 'action' => 'axell_form', 'score' => 0.7 ) );
		$this->assertTrue( Recaptcha::verify( $params, '1.2.3.4' ), 'The threshold itself passes.' );
		$this->assertSame( 'https://www.google.com/recaptcha/api/siteverify', $this->sent[0] );
		$this->assertSame( array( 'secret' => 'secret-v3', 'response' => 'token', 'remoteip' => '1.2.3.4' ), $this->sent[1] );

		$this->google( array( 'success' => true, 'action' => 'axell_form', 'score' => 0.6 ) );
		$this->assertInstanceOf( \WP_Error::class, Recaptcha::verify( $params, '1.2.3.4' ), 'Below the threshold.' );

		$this->google( array( 'success' => true, 'action' => 'login', 'score' => 0.9 ) );
		$this->assertInstanceOf( \WP_Error::class, Recaptcha::verify( $params, '1.2.3.4' ), 'Another action.' );

		$this->google( array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) ) );
		$this->assertInstanceOf( \WP_Error::class, Recaptcha::verify( $params, '1.2.3.4' ) );

		$this->google( null, 500 );
		$this->assertInstanceOf( \WP_Error::class, Recaptcha::verify( $params, '1.2.3.4' ), 'Google unreachable.' );
	}

	public function test_threshold_default_and_bounds(): void {
		$this->settings( array() );
		$this->assertSame( 0.5, Recaptcha::threshold() );
		$this->settings( array( 'recaptcha_v3_threshold' => '' ) );
		$this->assertSame( 0.5, Recaptcha::threshold() );
		$this->settings( array( 'recaptcha_v3_threshold' => 3 ) );
		$this->assertSame( 1.0, Recaptcha::threshold() );
	}

	public function test_v2_needs_only_success(): void {
		$this->settings( array( 'recaptcha_version' => 'v2' ) );
		$this->google( array( 'success' => true ) );

		$this->assertTrue( Recaptcha::verify( array( 'g-recaptcha-response' => 'token' ), '1.2.3.4' ) );
		$this->assertSame( 'secret-v2', $this->sent[1]['secret'] );
	}
}
