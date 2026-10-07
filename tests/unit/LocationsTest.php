<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Locations;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class LocationsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Approximates WordPress's real sanitize_title() (accent transliteration
	 * + slugify) closely enough for these tests — the real function isn't
	 * available outside a WP bootstrap.
	 */
	public static function fake_sanitize_title( string $title ): string {
		$accented = array( 'á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ' );
		$plain    = array( 'a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n' );
		$ascii    = str_replace( $accented, $plain, mb_strtolower( $title ) );
		return trim( preg_replace( '/[^a-z0-9]+/', '-', $ascii ), '-' );
	}

	public function test_register_taxonomy_registers_aa_location_as_hierarchical(): void {
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'register_taxonomy' )
			->once()
			->with(
				Locations::TAXONOMY,
				array(),
				\Mockery::on(
					function ( $args ) {
						return true === $args['hierarchical'] && false === $args['public'];
					}
				)
			);

		Locations::instance()->register_taxonomy();

		$this->addToAssertionCount( 1 );
	}

	public function test_states_returns_all_27_brazilian_states(): void {
		$states = Locations::instance()->states();

		$this->assertCount( 27, $states );
		$this->assertSame( 'São Paulo', $states['SP'] );
		$this->assertSame( 'Acre', $states['AC'] );
	}

	public function test_cities_for_state_returns_known_sao_paulo_city(): void {
		$cities = Locations::instance()->cities_for_state( 'SP' );

		$this->assertNotEmpty( $cities );
		$this->assertSame( 'São Paulo', $cities[3550308] );
	}

	public function test_cities_for_state_is_case_insensitive(): void {
		$upper = Locations::instance()->cities_for_state( 'RJ' );
		$lower = Locations::instance()->cities_for_state( 'rj' );

		$this->assertSame( $upper, $lower );
	}

	public function test_cities_for_state_unknown_uf_returns_empty_array(): void {
		$this->assertSame( array(), Locations::instance()->cities_for_state( 'ZZ' ) );
	}

	public function test_resolve_city_term_returns_null_for_unknown_state(): void {
		$this->assertNull( Locations::instance()->resolve_city_term( 'ZZ', 3550308 ) );
	}

	public function test_resolve_city_term_returns_null_for_unknown_city_code_in_a_real_state(): void {
		$this->assertNull( Locations::instance()->resolve_city_term( 'SP', 999999999 ) );
	}

	public function test_resolve_city_term_creates_and_returns_a_term_id_for_a_known_city(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_term_by' )->justReturn( false );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'sanitize_title' )->alias( array( self::class, 'fake_sanitize_title' ) );
		Functions\when( 'wp_insert_term' )->justReturn( array( 'term_id' => 42 ) );

		$term_id = Locations::instance()->resolve_city_term( 'SP', 3550308 );

		$this->assertSame( 42, $term_id );
	}

	public function test_resolve_city_term_uses_br_prefixed_slugs(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_term_by' )->justReturn( false );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'sanitize_title' )->alias( array( self::class, 'fake_sanitize_title' ) );

		$slugs = array();
		Functions\when( 'wp_insert_term' )->alias( function ( $name, $taxonomy, $args ) use ( &$slugs ) {
			$slugs[] = $args['slug'];
			return array( 'term_id' => count( $slugs ) );
		} );

		Locations::instance()->resolve_city_term( 'SP', 3550308 );

		$this->assertSame( array( 'br', 'br-sp', 'br-sp-sao-paulo' ), $slugs );
	}
}
