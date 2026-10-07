<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Reseller_Store;
use Axellcore_Atelierclub\Resellers_Import;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class ResellersImportTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'remove_accents' )->alias(
			static function ( string $text ): string {
				return str_replace( array( 'ã', 'á', 'í', 'ç', 'ê', 'é', 'ô', 'ó', 'ú', 'â' ), array( 'a', 'a', 'i', 'c', 'e', 'e', 'o', 'o', 'u', 'a' ), $text );
			}
		);
		Functions\when( 'sanitize_title' )->alias( array( LocationsTest::class, 'fake_sanitize_title' ) );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_bundled_export_rows_keep_values_as_stored(): void {
		$csv  = "\xEF\xBB\xBFNome,Status,País,Estado,Cidade,Endereço,\"Telefone 1\",\"Telefone 2\",Site,E-mail\n";
		$csv .= "\"Tonetto & Trova\",draft,Brasil,\"Rio Grande do sul\",\"Porto Alegre\",\" Av. X\t1 \",\" 54-3286\",,www.x.com.br,'@perfil\n";

		$rows   = Resellers_Import::read( $csv );
		$record = Resellers_Import::parse_row( $rows[0] );

		$this->assertSame( 'Tonetto & Trova', $record['title'] );
		$this->assertSame( 'draft', $record['status'] );
		$this->assertSame(
			array(
				'country' => 'Brasil',
				'state'   => 'Rio Grande do sul',
				'city'    => 'Porto Alegre',
			),
			$record['location']
		);
		$this->assertSame( " Av. X\t1 ", $record['meta']['endereco'] );
		$this->assertSame( ' 54-3286', $record['meta']['telefone-1'] );
		$this->assertSame( '', $record['meta']['telefone-2'] );
		$this->assertSame( 'www.x.com.br', $record['meta']['site'] );
		$this->assertSame( '@perfil', $record['meta']['e-mail'] );
	}

	public function test_axellcore_export_combined_columns_are_split(): void {
		$csv  = "ID,Nome,Localização,Endereço,Telefone,Site,E-mail\n";
		$csv .= "2508,Comercial Aluminox,Brasil > Minas Gerais > Montes Claros,Av. A,\"(38) 3221 1053, (38) 3222 5209\",,\n";

		$record = Resellers_Import::parse_row( Resellers_Import::read( $csv )[0] );

		$this->assertSame( 'publish', $record['status'] );
		$this->assertSame( 'Minas Gerais', $record['location']['state'] );
		$this->assertSame( 'Montes Claros', $record['location']['city'] );
		$this->assertSame( '(38) 3221 1053', $record['meta']['telefone-1'] );
		$this->assertSame( '(38) 3222 5209', $record['meta']['telefone-2'] );
	}

	public function test_row_without_name_is_skipped(): void {
		$this->assertNull( Resellers_Import::parse_row( array( 'nome' => '  ' ) ) );
	}

	public function test_state_name_accepts_uf_or_any_case(): void {
		$this->assertSame( 'Rio Grande do Sul', Reseller_Store::state_name( 'rs' ) );
		$this->assertSame( 'Rio Grande do Sul', Reseller_Store::state_name( 'Rio Grande do sul' ) );
		$this->assertSame( 'Antofagasta', Reseller_Store::state_name( 'Antofagasta' ) );
	}

	public function test_store_text_parses_name_uf_and_city(): void {
		$this->assertSame(
			array(
				'nome'   => 'Adair Beal',
				'uf'     => 'RS',
				'cidade' => 'passo fundo',
			),
			Reseller_Store::parse_store_text( 'Adair Beal - rs passo fundo' )
		);
		$this->assertNull( Reseller_Store::parse_store_text( 'texto livre' ) );
		$this->assertNull( Reseller_Store::parse_store_text( 'Loja - XX Cidade' ) );
	}

	public function test_normalize_location_uses_official_city_name(): void {
		$this->assertSame(
			array(
				'country' => 'Brasil',
				'state'   => 'Santa Catarina',
				'city'    => 'Florianópolis',
			),
			Reseller_Store::normalize_location( 'brasil', 'sc', 'florianopolis' )
		);
	}
}
