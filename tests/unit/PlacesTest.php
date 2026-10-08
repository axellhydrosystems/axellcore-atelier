<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Places;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class PlacesTest extends TestCase {

	/**
	 * Transients set in a test.
	 *
	 * @var array<string,mixed>
	 */
	private $transients = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'add_query_arg' )->alias( static fn( $args, $url ) => $url . '?' . http_build_query( $args ) );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( $r ) => $r['code'] );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( $r ) => $r['body'] );
		$this->transients = array();
		Functions\when( 'get_transient' )->alias( fn( $k ) => $this->transients[ $k ] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( $k, $v ) {
				$this->transients[ $k ] = $v;
				return true;
			}
		);
		$this->settings( array() );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function settings( array $values ): void {
		Functions\when( 'get_option' )->justReturn( $values + array( 'places_api_key' => 'key' ) );
	}

	private static function component( string $long, string $short, array $types ): array {
		return array( 'longText' => $long, 'shortText' => $short, 'types' => $types );
	}

	public function test_components_become_the_form_fields(): void {
		$fields = Places::fields(
			array(
				self::component( '1200', '1200', array( 'street_number' ) ),
				self::component( 'Rua XV de Novembro', 'R. XV de Novembro', array( 'route' ) ),
				self::component( 'Centro', 'Centro', array( 'sublocality_level_1', 'sublocality', 'political' ) ),
				self::component( 'Curitiba', 'Curitiba', array( 'administrative_area_level_2', 'political' ) ),
				self::component( 'Paraná', 'PR', array( 'administrative_area_level_1', 'political' ) ),
				self::component( 'Brasil', 'BR', array( 'country', 'political' ) ),
				self::component( '80045-270', '80045-270', array( 'postal_code' ) ),
			)
		);

		$this->assertSame(
			array(
				'address_street' => 'Rua XV de Novembro',
				'address_number' => '1200',
				'address_2'      => '',
				'neighborhood'   => 'Centro',
				'city'           => 'Curitiba',
				'state'          => 'PR',
				'postal'         => '80045270',
			),
			$fields
		);
	}

	public function test_an_apartment_is_the_complement(): void {
		$fields = Places::fields(
			array(
				self::component( 'ap 103', 'ap 103', array( 'subpremise' ) ),
				self::component( '34', '34', array( 'street_number' ) ),
				self::component( 'Rua Antônio Meras Sagas', 'R. Antônio Meras Sagas', array( 'route' ) ),
			)
		);
		$this->assertSame( 'ap 103', $fields['address_2'] );
		$this->assertSame( '34', $fields['address_number'] );
	}

	public function test_missing_parts_stay_empty(): void {
		$fields = Places::fields(
			array(
				self::component( 'Avenida Paulista', 'Av. Paulista', array( 'route' ) ),
				self::component( 'São Paulo', 'São Paulo', array( 'locality', 'political' ) ),
				self::component( 'São Paulo', 'SP', array( 'administrative_area_level_1' ) ),
				self::component( '01310', '01310', array( 'postal_code_prefix', 'postal_code' ) ),
			)
		);

		$this->assertSame( '', $fields['address_number'], 'No number: the cursor goes to Number.' );
		$this->assertSame( '', $fields['address_2'] );
		$this->assertSame( '', $fields['neighborhood'] );
		$this->assertSame( 'São Paulo', $fields['city'], 'The locality when there is no level 2.' );
		$this->assertSame( 'SP', $fields['state'] );
		$this->assertSame( '', $fields['postal'], 'Only a CEP prefix: left for the visitor.' );
	}

	public function test_suggestions_from_google_and_cached(): void {
		$calls = 0;
		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( &$calls ) {
				++$calls;
				$body = json_decode( $args['body'], true );
				$this->assertSame( 'https://places.googleapis.com/v1/places:autocomplete', $url );
				$this->assertSame( 'key', $args['headers']['X-Goog-Api-Key'] );
				$this->assertSame( array( 'br' ), $body['includedRegionCodes'] );
				$this->assertSame( 'session-1', $body['sessionToken'] );
				return array(
					'code' => 200,
					'body' => json_encode(
						array(
							'suggestions' => array(
								array(
									'placePrediction' => array(
										'placeId'          => 'abc',
										'structuredFormat' => array(
											'mainText'      => array( 'text' => 'Rua XV de Novembro, 1200' ),
											'secondaryText' => array( 'text' => 'Centro, Curitiba - PR, Brasil' ),
										),
									),
								),
								array( 'queryPrediction' => array( 'text' => array( 'text' => 'not a place' ) ) ),
							),
						)
					),
				);
			}
		);

		$expected = array(
			array(
				'id'        => 'abc',
				'main'      => 'Rua XV de Novembro, 1200',
				'secondary' => 'Centro, Curitiba - PR, Brasil',
			),
		);
		$this->assertSame( $expected, Places::suggestions( 'Rua XV  de Novembro 1200', 'session-1' ) );
		$this->assertSame( $expected, Places::suggestions( 'rua xv de novembro 1200', 'session-1' ), 'Same input in another case: from the cache.' );
		$this->assertSame( 1, $calls );
	}

	public function test_short_input_or_off_does_not_ask_google(): void {
		Functions\expect( 'wp_remote_post' )->never();

		$this->assertSame( array(), Places::suggestions( 'Rua' ) );

		$this->settings( array( 'places_enabled' => false ) );
		$this->assertSame( array(), Places::suggestions( 'Rua XV de Novembro' ) );

		Functions\when( 'get_option' )->justReturn( array() );
		$this->assertFalse( Places::active(), 'No key.' );
		$this->assertSame( array(), Places::suggestions( 'Rua XV de Novembro' ) );
	}

	public function test_google_errors_give_nothing(): void {
		Functions\when( 'wp_remote_post' )->justReturn( array( 'code' => 403, 'body' => '{"error":{}}' ) );
		$this->assertSame( array(), Places::suggestions( 'Rua XV de Novembro' ) );
		$this->assertSame( array(), $this->transients, 'Errors are not cached.' );

		Functions\when( 'wp_remote_get' )->justReturn( array( 'code' => 404, 'body' => '' ) );
		$this->assertNull( Places::address( 'abc' ) );
	}

	public function test_details_ask_only_the_address_and_reject_bad_ids(): void {
		Functions\when( 'wp_remote_get' )->alias(
			function ( $url, $args ) {
				$this->assertStringStartsWith( 'https://places.googleapis.com/v1/places/abc?', $url );
				$this->assertStringContainsString( 'sessionToken=session-1', $url );
				$this->assertSame( 'addressComponents', $args['headers']['X-Goog-FieldMask'] );
				return array(
					'code' => 200,
					'body' => json_encode( array( 'addressComponents' => array( self::component( 'Rua A', 'R. A', array( 'route' ) ) ) ) ),
				);
			}
		);

		$this->assertSame( 'Rua A', Places::address( 'abc', 'session-1' )['address_street'] );
		$this->assertNull( Places::address( '../abc' ), 'Only an id goes into the URL.' );
	}

	public function test_rate_limit_per_address(): void {
		for ( $i = 0; $i < Places::RATE_LIMIT; $i++ ) {
			$this->assertTrue( Places::check_rate_limit( '203.0.113.5' ) );
		}
		$limited = Places::check_rate_limit( '203.0.113.5' );
		$this->assertInstanceOf( \WP_Error::class, $limited );
		$this->assertSame( 'aa_rate_limited', $limited->get_error_code() );
		$this->assertTrue( Places::check_rate_limit( '203.0.113.6' ), 'Another address has its own count.' );
	}
}
