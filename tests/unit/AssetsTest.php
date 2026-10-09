<?php
/**
 * @package Axellcore_Atelier\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelier\Tests;

use Axellcore_Atelier\Assets;
use Axellcore_Atelier\Plugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// No Atelier page chosen nor found (Classic_Template asks Settings).
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'get_page_by_path' )->justReturn( null );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_enqueue_frontend_assets_does_nothing_off_our_template(): void {
		Functions\when( 'is_page_template' )->justReturn( false );
		// Not a singular page, so no form page either (frontend.js only loads there).
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();

		Assets::instance()->enqueue_frontend_assets();

		$this->addToAssertionCount( 1 );
	}

	public function test_enqueue_frontend_assets_enqueues_only_frontend_js_on_our_template(): void {
		Functions\when( 'is_page_template' )->alias(
			function ( $slug ) {
				return Plugin::TEMPLATE_SLUG === $slug;
			}
		);

		// The landing uses the theme's global styles: no stylesheet of its own.
		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_enqueue_script' )
			->with( 'aa-frontend', \Mockery::type( 'string' ), array(), AXELLCORE_ATELIER_VERSION, \Mockery::type( 'array' ) )
			->once();

		Functions\when( 'rest_url' )->justReturn( 'https://example.com/wp-json/axellcore-atelier/v1' );
		Functions\when( 'trailingslashit' )->justReturn( 'https://example.com/wp-json/axellcore-atelier/v1/' );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\expect( 'wp_localize_script' )
			->with( 'aa-frontend', 'aaRest', array( 'root' => 'https://example.com/wp-json/axellcore-atelier/v1/' ) )
			->once();

		Assets::instance()->enqueue_frontend_assets();

		$this->addToAssertionCount( 1 );
	}

}
