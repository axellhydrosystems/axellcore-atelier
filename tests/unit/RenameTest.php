<?php
/**
 * @package Axellcore_Atelier\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelier\Tests;

use Axellcore_Atelier\Rename;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class RenameTest extends TestCase {

	/**
	 * @var object
	 */
	private $wpdb;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->wpdb = new class() {
			public $options  = 'wp_options';
			public $postmeta = 'wp_postmeta';
			public $updates  = array();
			public function update( $table, $data, $where ) {
				$this->updates[] = array( $table, $data, $where );
				return 1;
			}
		};
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_nothing_happens_without_the_former_version(): void {
		Functions\when( 'get_option' )->justReturn( false );
		Functions\expect( 'wp_cache_flush' )->never();

		Rename::migrate();

		$this->assertSame( array(), $this->wpdb->updates );
	}

	public function test_options_meta_and_template_are_renamed(): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name ) {
				return 0 === strpos( $name, 'axellcore_atelierclub_' ) ? 'old' : false;
			}
		);
		Functions\expect( 'delete_option' )->never();
		Functions\expect( 'wp_cache_flush' )->once();

		Rename::migrate();

		$this->assertContains( array( 'wp_options', array( 'option_name' => 'axellcore_atelier_settings' ), array( 'option_name' => 'axellcore_atelierclub_settings' ) ), $this->wpdb->updates );
		$this->assertContains( array( 'wp_options', array( 'option_name' => 'axellcore_atelier_version' ), array( 'option_name' => 'axellcore_atelierclub_version' ) ), $this->wpdb->updates );
		$this->assertContains( array( 'wp_postmeta', array( 'meta_key' => '_axellcore_atelier_media' ), array( 'meta_key' => '_axellcore_atelierclub_media' ) ), $this->wpdb->updates );
		$this->assertContains(
			array(
				'wp_postmeta',
				array( 'meta_value' => 'atelier' ),
				array(
					'meta_key'   => '_wp_page_template',
					'meta_value' => 'atelier-club',
				),
			),
			$this->wpdb->updates
		);
	}

	public function test_an_option_already_under_the_new_name_drops_the_former(): void {
		Functions\when( 'get_option' )->justReturn( 'set' );
		Functions\expect( 'delete_option' )->times( count( Rename::OPTIONS ) );
		Functions\when( 'wp_cache_flush' )->justReturn( true );

		Rename::migrate();

		$this->assertCount( count( Rename::META ) + 1, $this->wpdb->updates );
	}
}
