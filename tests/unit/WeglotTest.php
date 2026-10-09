<?php
/**
 * @package Axellcore_Atelier\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelier\Tests;

use Axellcore_Atelier\Weglot;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class WeglotTest extends TestCase {

	/**
	 * REQUEST_URI before the test.
	 *
	 * @var string|null
	 */
	private $request_uri;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->request_uri = $_SERVER['REQUEST_URI'] ?? null;
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'untrailingslashit' )->alias(
			function ( $value ) {
				return rtrim( $value, '/\\' );
			}
		);
		Functions\when( 'home_url' )->justReturn( 'https://example.com/' );
		$this->atelier_page( 'atelier' );
	}

	/**
	 * The Atelier page chosen in the settings (ID 7) with this page URI, or none.
	 *
	 * @param string|null $uri Page URI, null for no page.
	 */
	private function atelier_page( ?string $uri ): void {
		Functions\when( 'absint' )->alias( 'intval' );
		Functions\when( 'get_option' )->justReturn( null === $uri ? array() : array( 'page_id' => 7 ) );
		Functions\when( 'get_post' )->alias(
			static function ( $id ) use ( $uri ) {
				return null !== $uri && 7 === (int) $id ? new \WP_Post( array( 'ID' => 7 ) ) : null;
			}
		);
		Functions\when( 'get_page_by_path' )->justReturn( null );
		Functions\when( 'get_page_uri' )->justReturn( (string) $uri );
	}

	protected function tearDown(): void {
		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_cancel_init_on_atelier_and_its_children(): void {
		foreach ( array( '/atelier', '/atelier/', '/atelier/adesao/', '/atelier/?utm_source=x' ) as $uri ) {
			$_SERVER['REQUEST_URI'] = $uri;
			$this->assertTrue( Weglot::instance()->cancel_init( false ), $uri );
		}
	}

	public function test_cancel_init_leaves_other_pages_alone(): void {
		foreach ( array( '/', '/produtos/', '/atelier-x/', '/en/atelier/', '/blog/atelier/' ) as $uri ) {
			$_SERVER['REQUEST_URI'] = $uri;
			$this->assertFalse( Weglot::instance()->cancel_init( false ), $uri );
		}
	}

	public function test_cancel_init_strips_the_home_path_of_a_subdirectory_install(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.com/site/' );
		$_SERVER['REQUEST_URI'] = '/site/atelier/';
		$this->assertTrue( Weglot::instance()->cancel_init( false ) );
	}

	public function test_cancel_init_keeps_weglot_in_the_admin_and_an_earlier_cancel(): void {
		$_SERVER['REQUEST_URI'] = '/atelier/';
		$this->assertTrue( Weglot::instance()->cancel_init( true ) );

		Functions\when( 'is_admin' )->justReturn( true );
		$this->assertFalse( Weglot::instance()->cancel_init( false ) );
	}

	public function test_the_path_follows_the_page_slug(): void {
		$this->atelier_page( 'clube-atelier' );

		$_SERVER['REQUEST_URI'] = '/clube-atelier/';
		$this->assertTrue( Weglot::instance()->cancel_init( false ) );
		$_SERVER['REQUEST_URI'] = '/atelier/';
		$this->assertFalse( Weglot::instance()->cancel_init( false ) );
		$this->assertSame( '^/clube\\-atelier(/|$)', Weglot::instance()->exclude_urls( array() )[0][0] );
	}

	public function test_without_an_atelier_page_nothing_is_cancelled_or_excluded(): void {
		$this->atelier_page( null );

		$_SERVER['REQUEST_URI'] = '/atelier/';
		$this->assertFalse( Weglot::instance()->cancel_init( false ) );
		$this->assertSame( array(), Weglot::instance()->exclude_urls( array() ) );
	}

	public function test_exclude_urls_adds_atelier_without_the_switcher(): void {
		$existing = array( array( '/wp-login.php', null ) );
		$result   = Weglot::instance()->exclude_urls( $existing );

		$this->assertSame( $existing[0], $result[0] );
		$this->assertSame( array( '^/atelier(/|$)', null, 'NOT_TRANSLATED', false ), $result[1] );
		$this->assertSame( 1, preg_match( '#' . $result[1][0] . '#', '/atelier/adesao/' ) );
		$this->assertSame( 1, preg_match( '#' . $result[1][0] . '#', '/atelier' ) );
		$this->assertSame( 0, preg_match( '#' . $result[1][0] . '#', '/atelier-x/' ) );
	}
}
