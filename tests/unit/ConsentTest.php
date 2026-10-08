<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Form_Submission;
use Axellcore_Atelierclub\Members;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class ConsentTest extends TestCase {

	/**
	 * User meta written in a test.
	 *
	 * @var array<string,string>
	 */
	private $meta = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->meta = array();
		Functions\stubTranslationFunctions();
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		Functions\when( 'sanitize_textarea_field' )->alias( 'trim' );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'update_user_meta' )->alias(
			function ( $user_id, $key, $value ) {
				$this->meta[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_user_meta' )->alias(
			function ( $user_id, $key ) {
				return $this->meta[ $key ] ?? '';
			}
		);
		Functions\when( 'get_option' )->justReturn( 'd/m/Y' );
		Functions\when( 'wp_date' )->alias( static fn( $format, $time ) => gmdate( 'd/m/Y H:i', $time ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_terms_come_from_the_saved_consent_checkbox(): void {
		$form = array(
			'innerHTML'   => '<form></form>',
			'innerBlocks' => array(
				array( 'innerHTML' => '<div><input type="text" name="fullname"/></div>' ),
				array(
					'innerHTML' => '<div class="wp-block-axell-form-check"><input type="checkbox" id="consent" name="consent" required/><label for="consent">Li e concordo com o <a href="https://axell.com.br/regulamento">regulamento</a> e com a <a href="#">Política</a>  de&nbsp;Privacidade.</label></div>',
				),
			),
		);

		$terms = Form_Submission::consent_terms( $form );

		$this->assertSame( 'Li e concordo com o regulamento e com a Política de Privacidade.', $terms['text'] );
		$this->assertSame( array( 'https://axell.com.br/regulamento' ), $terms['links'], 'A "#" link is no address.' );
	}

	public function test_a_form_without_consent_gives_no_terms(): void {
		$this->assertSame( array( 'text' => '', 'links' => array() ), Form_Submission::consent_terms( array( 'innerHTML' => '<form><input name="email"/></form>' ) ) );
	}

	public function test_record_and_summary(): void {
		$this->assertSame( '', Members::consent_summary( 7 ), 'Nothing recorded: no summary.' );

		Members::record_consent(
			7,
			array(
				'text'  => 'Li e concordo.',
				'links' => array( 'https://axell.com.br/regulamento' ),
				'ip'    => '203.0.113.9',
				'url'   => 'https://axell.com.br/atelier/',
			)
		);

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $this->meta['aa_consent_at'], 'Stored in UTC.' );
		$this->assertSame( 'Li e concordo.', $this->meta['aa_consent_text'] );
		$this->assertSame( '203.0.113.9', $this->meta['aa_consent_ip'] );

		$summary = explode( "\n", Members::consent_summary( 7 ) );
		$this->assertStringStartsWith( 'Accepted on ', $summary[0] );
		$this->assertStringContainsString( 'IP 203.0.113.9, at https://axell.com.br/atelier/', $summary[0] );
		$this->assertSame( '“Li e concordo.”', $summary[1] );
		$this->assertSame( 'https://axell.com.br/regulamento', $summary[2] );
	}

	public function test_without_terms_only_the_moment_is_recorded(): void {
		Members::record_consent( 8, array() );

		$this->assertArrayHasKey( 'aa_consent_at', $this->meta );
		$this->assertArrayNotHasKey( 'aa_consent_text', $this->meta );
		$this->assertArrayNotHasKey( 'aa_consent_ip', $this->meta );
	}
}
