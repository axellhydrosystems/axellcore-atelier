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
		$this->assertSame(
			array(
				array(
					'label' => 'regulamento',
					'url'   => 'https://axell.com.br/regulamento',
				),
				array(
					'label' => 'Política',
					'url'   => '#',
				),
			),
			$terms['links'],
			'A "#" link is kept as written.'
		);
	}

	public function test_a_form_without_consent_gives_no_terms(): void {
		$this->assertSame( array( 'text' => '', 'links' => array() ), Form_Submission::consent_terms( array( 'innerHTML' => '<form><input name="email"/></form>' ) ) );
	}

	public function test_one_line_of_five_fields(): void {
		Members::record_consent(
			7,
			array(
				'text'  => 'Li e concordo.',
				'links' => array(
					array( 'label' => 'regulamento', 'url' => 'http://axell.com.br/regulamento' ),
					array( 'label' => 'Política de Privacidade', 'url' => 'https://axell.com.br/privacidade' ),
				),
				'ip'    => '203.0.113.9',
				'url'   => 'https://axell.com.br/atelier/',
			)
		);

		$line = $this->meta['consent'];
		$this->assertMatchesRegularExpression( Members::CONSENT_PATTERN, $line );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\|/', $line, 'When, in UTC (ISO 8601).' );
		$this->assertStringEndsWith( '|203.0.113.9|https://axell.com.br/atelier/|Li e concordo.|regulamento:http://axell.com.br/regulamento,Política de Privacidade:https://axell.com.br/privacidade', $line );
		$this->assertArrayNotHasKey( 'aa_consent_at', $this->meta, 'One meta only.' );

		$consent = Members::consent( 7 );
		$this->assertSame( '203.0.113.9', $consent['ip'] );
		$this->assertSame( 'https://axell.com.br/atelier/', $consent['url'] );
		$this->assertSame( 'Li e concordo.', $consent['text'] );
		$this->assertSame( 'http://axell.com.br/regulamento', $consent['links'][0]['url'], 'The url keeps its ":".' );
		$this->assertSame( 'Política de Privacidade', $consent['links'][1]['label'] );
	}

	public function test_escapes_read_back_the_same(): void {
		Members::record_consent(
			7,
			array(
				'text'  => 'Aceito A|B e C\\D.',
				'links' => array( array( 'label' => 'termos: 1, 2|3', 'url' => 'https://x.com/a?b=1,2|3' ) ),
			)
		);

		$line = $this->meta['consent'];
		$this->assertStringContainsString( '|Aceito A\\|B e C\\\\D.|', $line, 'The text\'s "|" and "\\" are escaped.' );
		$this->assertStringContainsString( '|termos\\: 1\\, 2\\|3:https://x.com/a?b=1%2C2%7C3', $line, 'A label\'s ":", "," and "|" escaped; a url\'s "," and "|" encoded.' );

		$consent = Members::consent( 7 );
		$this->assertSame( 'Aceito A|B e C\\D.', $consent['text'] );
		$this->assertSame( array( array( 'label' => 'termos: 1, 2|3', 'url' => 'https://x.com/a?b=1,2|3' ) ), $consent['links'] );
		$this->assertSame( '', $consent['ip'], 'No IP: an empty field.' );
	}

	public function test_no_links_leave_the_last_field_empty(): void {
		Members::record_consent( 8, array( 'text' => 'Sim.' ) );

		$this->assertStringEndsWith( '|||Sim.|', $this->meta['consent'] );
		$this->assertSame( array(), Members::consent( 8 )['links'] );
	}

	public function test_nothing_or_a_broken_line_is_no_record(): void {
		$this->assertSame( '', Members::consent( 9 )['at'] );
		$this->assertSame( '', Members::consent_summary( 9 ) );
		$this->meta['consent'] = 'not a consent line';
		$this->assertSame( '', Members::consent( 9 )['at'] );
	}

	public function test_summary(): void {
		Members::record_consent(
			7,
			array(
				'text'  => 'Li e concordo com o regulamento.',
				'links' => array( array( 'label' => 'regulamento', 'url' => 'https://axell.com.br/regulamento' ) ),
				'ip'    => '203.0.113.9',
				'url'   => 'https://axell.com.br/atelier/',
			)
		);

		$summary = explode( "\n", Members::consent_summary( 7 ) );
		$this->assertStringStartsWith( 'Accepted on ', $summary[0] );
		$this->assertStringContainsString( 'IP 203.0.113.9, at https://axell.com.br/atelier/', $summary[0] );
		$this->assertSame( '“Li e concordo com o <a href="https://axell.com.br/regulamento">regulamento</a>.”', $summary[1] );
		$this->assertCount( 2, $summary, 'The link is in the text.' );
	}

	public function test_links_are_anchors_in_the_text(): void {
		$consent = array(
			'text'  => 'Li o regulamento e a Política de regulamento.',
			'links' => array(
				array( 'label' => 'regulamento', 'url' => '#' ),
				array( 'label' => 'Política', 'url' => 'https://axell.com.br/p' ),
				array( 'label' => 'termos', 'url' => 'https://axell.com.br/t' ),
			),
		);

		$this->assertSame(
			array( 'Li o <a href="#">regulamento</a> e a <a href="https://axell.com.br/p">Política</a> de regulamento.', array( '<a href="https://axell.com.br/t">termos</a>' ) ),
			Members::consent_links( $consent ),
			'Each link where it is in the text, in order; one not found comes apart.'
		);
	}
}
