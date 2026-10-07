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

final class MembersResellersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $v ) => trim( (string) $v ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_empty_positions_are_removed_and_order_kept(): void {
		$stores = Members::submitted_resellers(
			array(
				'reseller1'       => '',
				'reseller1_title' => '',
				'reseller2'       => '363',
				'reseller2_title' => 'Dekasa · RS Santiago',
				'reseller3'       => '',
				'reseller3_title' => '',
				'reseller4'       => '',
				'reseller4_title' => 'Atelier dos Pisos - RS Erechim',
				'reseller5'       => '',
				'reseller5_title' => '  ',
			)
		);

		$this->assertSame(
			array(
				array(
					'id'    => 363,
					'title' => 'Dekasa · RS Santiago',
				),
				array(
					'id'    => 0,
					'title' => 'Atelier dos Pisos - RS Erechim',
				),
			),
			$stores
		);
	}

	public function test_all_filled_stay_in_place(): void {
		$params = array();
		foreach ( range( 1, 5 ) as $n ) {
			$params[ "reseller{$n}" ]       = (string) ( 100 + $n );
			$params[ "reseller{$n}_title" ] = "Loja {$n}";
		}

		$stores = Members::submitted_resellers( $params );

		$this->assertCount( 5, $stores );
		$this->assertSame( array( 101, 102, 103, 104, 105 ), array_column( $stores, 'id' ) );
	}

	public function test_no_store_gives_empty_list(): void {
		$this->assertSame( array(), Members::submitted_resellers( array() ) );
	}
}
