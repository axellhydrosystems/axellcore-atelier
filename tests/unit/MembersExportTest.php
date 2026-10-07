<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Members_Export;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class MembersExportTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_row_has_labels_masked_document_and_reseller_text(): void {
		$meta = array(
			'company'          => '=HYPERLINK("x")',
			'primary_focus'    => 'interior_design',
			'profile_type'     => 'legal_entity',
			'br_revenue_id'    => '11222333000181',
			'state'            => 'MG',
			'city'             => 'Belo Horizonte',
			'reseller1_title'  => 'Loja · MG BH',
			'reseller3_title'  => 'Casa, Banho · SP',
		);
		Functions\when( 'get_date_from_gmt' )->returnArg( 1 );
		Functions\when( 'get_user_meta' )->alias(
			static function ( $id, $key ) use ( $meta ) {
				return $meta[ $key ] ?? '';
			}
		);
		$user                  = new \WP_User();
		$user->ID              = 4;
		$user->roles           = array( 'member' );
		$user->display_name    = 'Beatriz Lima';
		$user->user_email      = 'contato@limainteriores.com';

		$row = Members_Export::row_for( $user, array( 'id', 'status', 'fullname', 'company', 'primary_focus', 'profile_type', 'br_revenue_id', 'city', 'resellers' ) );

		$this->assertSame(
			array( '4', 'Membro', 'Beatriz Lima', "'=HYPERLINK(\"x\")", 'Design de interiores', 'Pessoa Jurídica · CNPJ', '11.222.333/0001-81', 'Belo Horizonte', 'Loja · MG BH, Casa\\, Banho · SP' ),
			$row
		);
	}

	public function test_csv_line_quotes_only_when_needed(): void {
		$this->assertSame( "a,\"b,c\",\"d\"\"e\"\n", Members_Export::csv_line( array( 'a', 'b,c', 'd"e' ) ) );
	}

	public function test_cpf_is_masked(): void {
		$this->assertSame( '529.982.247-25', Members_Export::format_document( '52998224725' ) );
	}

	public function test_default_columns_leave_out_the_technical_ones(): void {
		$defaults = Members_Export::default_columns();

		$this->assertNotContains( 'id', $defaults );
		$this->assertNotContains( 'registered', $defaults );
		$this->assertNotContains( 'login', $defaults );
		$this->assertNotContains( 'country', $defaults );
		$this->assertSame( 'fullname', $defaults[1] );
		$this->assertSame( 'resellers', end( $defaults ) );
	}
}
