<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Format;
use Axellcore_Atelierclub\Members;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase {

	public function test_phone_is_stored_with_55_and_shown_with_its_mask(): void {
		$this->assertSame( '+5511987654321', Format::phone_store( '(11) 98765-4321' ) );
		$this->assertSame( '+5511987654321', Format::phone_store( '+55 11 98765-4321' ), 'A pasted country code counts once.' );
		$this->assertSame( '+551134567890', Format::phone_store( '1134567890' ) );
		$this->assertSame( '', Format::phone_store( '' ) );
		$this->assertSame( '(11) 98765-4321', Format::phone( '+5511987654321' ) );
		$this->assertSame( '(11) 3456-7890', Format::phone( '+551134567890' ) );
	}

	public function test_cep_and_documents_get_their_masks(): void {
		$this->assertSame( '01001-000', Format::postcode( '01001000' ) );
		$this->assertSame( '529.982.247-25', Format::document( '52998224725' ) );
		$this->assertSame( '11.222.333/0001-81', Format::document( '11222333000181' ) );
		$this->assertSame( '12.ABC.345/01DE-35', Format::document( '12ABC34501DE35' ) );
	}

	public function test_name_and_profile_type_follow_the_rules(): void {
		$this->assertSame( array( 'Ana', 'Maria Souza' ), Members::split_name( ' Ana Maria Souza ' ) );
		$this->assertSame( array( 'Ana', '' ), Members::split_name( 'Ana' ) );
		$this->assertSame( 'individual', Members::profile_type_of( '529.982.247-25' ) );
		$this->assertSame( 'legal_entity', Members::profile_type_of( '12.ABC.345/01DE-35' ) );
		$this->assertSame( '', Members::profile_type_of( '529.982.247' ) );
	}

	public function test_url_gets_https_and_must_be_an_address(): void {
		Monkey\setUp();
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$this->assertSame( 'https://www.atelierhelena.com.br/portfolio', Format::url( 'www.atelierhelena.com.br/portfolio' ), 'No scheme: https://.' );
		$this->assertSame( 'https://instagram.com/ana', Format::url( ' instagram.com/ana ' ) );
		$this->assertSame( 'http://site.com.br', Format::url( 'http://site.com.br' ), 'A scheme typed stays.' );
		$this->assertSame( '', Format::url( '' ), 'Empty is allowed (optional field).' );
		$this->assertNull( Format::url( 'meu portfolio' ) );
		$this->assertNull( Format::url( 'localhost' ), 'A host without a dot.' );
		$this->assertNull( Format::url( 'ftp://site.com.br' ) );
		Monkey\tearDown();
	}

	public function test_text_links_are_links_again(): void {
		Monkey\setUp();
		Functions\when( 'esc_url' )->alias( static fn( $url ) => 0 === strpos( $url, 'javascript:' ) ? '' : htmlspecialchars( $url, ENT_QUOTES ) );
		$escaped = htmlspecialchars( 'Li o <a href="#">regulamento</a>, a <a href="https://x.com/?a=1&b=2">Política</a> e <a href="javascript:alert(1)">isto</a> <b>x</b>.', ENT_QUOTES );

		$this->assertSame(
			'Li o <a href="#">regulamento</a>, a <a href="https://x.com/?a=1&amp;b=2">Política</a> e &lt;a href=&quot;javascript:alert(1)&quot;&gt;isto&lt;/a&gt; &lt;b&gt;x&lt;/b&gt;.',
			Format::text_links( $escaped ),
			'Only links, only through esc_url().'
		);
		Monkey\tearDown();
	}
}
