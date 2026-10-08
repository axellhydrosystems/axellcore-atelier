<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Document;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase {

	public function test_cpf_check_digits(): void {
		$this->assertTrue( Document::is_valid( '529.982.247-25', 'cpf' ) );
		$this->assertTrue( Document::is_valid( '11144477735', 'cpf' ) );
		$this->assertFalse( Document::is_valid( '529.982.247-24', 'cpf' ) );
		$this->assertFalse( Document::is_valid( '111.111.111-11', 'cpf' ) );
		$this->assertFalse( Document::is_valid( '5299822472', 'cpf' ) );
	}

	public function test_numeric_and_alphanumeric_cnpj_check_digits(): void {
		$this->assertTrue( Document::is_valid( '11.222.333/0001-81', 'cnpj' ) );
		// Receita Federal's example of the alphanumeric CNPJ (from July 2026).
		$this->assertTrue( Document::is_valid( '12.ABC.345/01DE-35', 'cnpj' ) );
		$this->assertTrue( Document::is_valid( '12abc34501de35', 'cnpj' ) );
		$this->assertFalse( Document::is_valid( '12.ABC.345/01DE-36', 'cnpj' ) );
		$this->assertFalse( Document::is_valid( '11.111.111/1111-11', 'cnpj' ) );
		$this->assertFalse( Document::is_valid( '12.ABC.345/01DE-3A', 'cnpj' ) );
	}

	public function test_the_type_must_match_the_document(): void {
		$this->assertFalse( Document::is_valid( '529.982.247-25', 'cnpj' ) );
		$this->assertFalse( Document::is_valid( '11.222.333/0001-81', 'cpf' ) );
		$this->assertTrue( Document::is_valid( '11.222.333/0001-81', '' ) );
		$this->assertTrue( Document::is_valid( '529.982.247-25', '' ) );
	}

	public function test_type_of_the_profile_type_field(): void {
		$this->assertSame( 'cpf', Document::type_of( 'individual' ) );
		$this->assertSame( 'cnpj', Document::type_of( 'legal_entity' ) );
		$this->assertSame( 'cnpj', Document::type_of( 'CNPJ' ) );
		$this->assertSame( '', Document::type_of( '' ) );
	}

	public function test_normalize_keeps_letters_and_digits_upper_case(): void {
		$this->assertSame( '52998224725', Document::normalize( '529.982.247-25' ) );
		$this->assertSame( '12ABC34501DE35', Document::normalize( '12.abc.345/01de-35' ) );
	}
}
