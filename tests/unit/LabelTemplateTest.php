<?php
/**
 * @package Axellcore_Atelier\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelier\Tests;

use Axellcore_Atelier\Form_Directives;
use Axellcore_Atelier\Label_Template;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class LabelTemplateTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_title' )->alias(
			static function ( $value ) {
				$ascii = (string) iconv( 'UTF-8', 'ASCII//TRANSLIT', (string) $value );
				return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $ascii ) ), '-' );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The reseller label for a store in this state (slug, name) and country.
	 *
	 * @param string      $slug    State term slug.
	 * @param string      $name    State term name.
	 * @param string|null $country Country term slug, null for none.
	 * @return string
	 */
	private function label( string $slug, string $name, ?string $country ): string {
		Functions\when( 'get_the_terms' )->alias(
			static function ( $id, $taxonomy ) use ( $slug, $name, $country ) {
				switch ( $taxonomy ) {
					case 'estados':
						return array( (object) array( 'slug' => $slug, 'name' => $name ) );
					case 'cidades':
						return array( (object) array( 'slug' => 'cidade', 'name' => 'Cidade' ) );
					default:
						return null === $country ? false : array( (object) array( 'slug' => $country, 'name' => $country ) );
				}
			}
		);
		$post = new \WP_Post( array( 'ID' => 1, 'post_title' => 'Loja' ) );
		return Label_Template::render( $post, Form_Directives::RESELLER_TEMPLATE );
	}

	public function test_a_state_slug_with_its_full_name_gives_the_uf(): void {
		$this->assertSame( 'Loja · RJ Cidade', $this->label( 'rio-de-janeiro', 'Rio de Janeiro', null ) );
		$this->assertSame( 'Loja · SC Cidade', $this->label( 'santa-catarina', 'Santa Catarina', 'brasil' ) );
		$this->assertSame( 'Loja · ES Cidade', $this->label( 'espirito-santo', 'Espírito Santo', 'brasil' ) );
	}

	public function test_a_uf_slug_stays(): void {
		$this->assertSame( 'Loja · RS Cidade', $this->label( 'rs', 'Rio Grande do Sul', 'brasil' ) );
	}

	public function test_a_state_abroad_shows_its_name(): void {
		$this->assertSame( 'Loja · Antofagasta Cidade', $this->label( 'antofagasta', 'Antofagasta', 'chile' ) );
		$this->assertSame( 'Loja · Distrito Federal Cidade', $this->label( 'distrito-federal', 'Distrito Federal', 'mexico' ), 'Not DF outside Brazil.' );
	}
}
