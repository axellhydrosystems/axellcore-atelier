<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Settings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'absint' )->alias( 'intval' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_page_is_the_chosen_one(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->alias(
			static function ( $id ) {
				return 12 === (int) $id ? new \WP_Post( array( 'ID' => 12, 'post_name' => 'clube' ) ) : null;
			}
		);
		Functions\expect( 'get_page_by_path' )->never();

		$this->assertSame( 12, Settings::page()->ID );
	}

	public function test_without_a_choice_page_is_the_atelier_slug_and_never_the_global_post(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		// get_post( 0 ) would return the post being viewed.
		Functions\expect( 'get_post' )->never();
		Functions\when( 'get_page_by_path' )->justReturn( new \WP_Post( array( 'ID' => 5, 'post_name' => 'atelier' ) ) );
		// Remembered, so a new slug later keeps it the Atelier page.
		Functions\expect( 'update_option' )->once()->with( Settings::OPTION, array( 'page_id' => 5 ) );

		$this->assertSame( 5, Settings::page()->ID );
	}

	public function test_a_trashed_choice_falls_back_to_the_slug(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->justReturn( new \WP_Post( array( 'ID' => 12, 'post_status' => 'trash' ) ) );
		Functions\when( 'get_page_by_path' )->justReturn( null );

		$this->assertNull( Settings::page() );
	}

	public function test_is_page(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->alias(
			static function ( $post ) {
				if ( $post instanceof \WP_Post ) {
					return $post;
				}
				return 12 === (int) $post ? new \WP_Post( array( 'ID' => 12 ) ) : null;
			}
		);

		$this->assertTrue( Settings::is_page( 12 ) );
		$this->assertFalse( Settings::is_page( new \WP_Post( array( 'ID' => 13 ) ) ) );
		$this->assertFalse( Settings::is_page( 0 ) );
	}
}
