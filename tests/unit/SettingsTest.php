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

	public function test_without_a_choice_there_is_no_page(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 0 ) );
		// get_post( 0 ) would return the post being viewed; no guess by slug either.
		Functions\expect( 'get_post' )->never();
		Functions\expect( 'get_page_by_path' )->never();

		$this->assertNull( Settings::page() );
		$this->assertFalse( Settings::never_configured() );
	}

	public function test_never_configured_until_the_page_setting_exists(): void {
		Functions\when( 'get_option' )->justReturn( false );
		$this->assertTrue( Settings::never_configured() );

		Functions\when( 'get_option' )->justReturn( array( 'pending_on_create' => true ) );
		$this->assertTrue( Settings::never_configured() );
		$this->assertNull( Settings::page() );
	}

	public function test_a_trashed_choice_is_no_page(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->justReturn( new \WP_Post( array( 'ID' => 12, 'post_status' => 'trash' ) ) );
		Functions\expect( 'get_page_by_path' )->never();

		$this->assertNull( Settings::page() );
	}

	public function test_post_state_marks_only_the_atelier_page(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->alias(
			static function ( $post ) {
				if ( $post instanceof \WP_Post ) {
					return $post;
				}
				return 12 === (int) $post ? new \WP_Post( array( 'ID' => 12 ) ) : null;
			}
		);
		$front = array( 'page_on_front' => 'Front Page' );

		$this->assertSame( $front + array( 'axellcore_atelierclub_page' => 'Atelier Page' ), Settings::instance()->post_state( $front, new \WP_Post( array( 'ID' => 12 ) ) ) );
		$this->assertSame( $front, Settings::instance()->post_state( $front, new \WP_Post( array( 'ID' => 13 ) ) ) );
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

	public function test_sanitize_keeps_the_access_setting_as_a_boolean(): void {
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'get_post_type' )->justReturn( 'page' );

		$on  = Settings::instance()->sanitize( array( 'members_can_log_in' => '1', 'other' => 'x' ) );
		$off = Settings::instance()->sanitize( array( 'members_can_log_in' => '0' ) );

		$this->assertTrue( $on['members_can_log_in'] );
		$this->assertFalse( $off['members_can_log_in'] );
		$this->assertArrayNotHasKey( 'other', $on );
		$this->assertFalse( Settings::DEFAULTS['members_can_log_in'], 'Off by default.' );
	}
}
