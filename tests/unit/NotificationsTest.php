<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Member;
use Axellcore_Atelierclub\Notifications;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class NotificationsTest extends TestCase {

	/**
	 * Settings saved.
	 *
	 * @var array<string,mixed>
	 */
	private $settings = array();

	/**
	 * E-mails "sent": to, subject, message, headers.
	 *
	 * @var array<int,array<int,mixed>>
	 */
	private $sent = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->settings = array();
		$this->sent     = array();
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( '_x' )->returnArg( 1 );
		Functions\stubEscapeFunctions();
		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) {
				if ( 'axellcore_atelierclub_settings' === $name ) {
					return $this->settings;
				}
				return 'admin_email' === $name ? 'admin@axell.com.br' : ( 'date_format' === $name ? 'd/m/Y' : $default );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Axell' );
		// The bundled logo is not in the media library unless a test says so.
		Functions\when( 'get_posts' )->justReturn( array() );
		Functions\when( 'is_email' )->alias( static fn( $e ) => false !== strpos( (string) $e, '@' ) );
		Functions\when( 'wp_specialchars_decode' )->returnArg( 1 );
		Functions\when( 'home_url' )->justReturn( 'https://axell.com.br/' );
		Functions\when( 'admin_url' )->alias( static fn( $p ) => 'https://axell.com.br/wp-admin/' . $p );
		Functions\when( 'wp_date' )->justReturn( '08/10/2026' );
		Functions\when( 'get_post' )->justReturn( null );
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'make_clickable' )->alias( static fn( $t ) => preg_replace( '#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $t ) );
		Functions\when( 'language_attributes' )->justReturn( null );
		Functions\when( 'is_rtl' )->justReturn( false );
		Functions\when( 'get_permalink' )->justReturn( '' );
		Functions\when( 'wp_mail' )->alias(
			function ( $to, $subject, $message, $headers ) {
				$this->sent[] = array( $to, $subject, $message, $headers );
				return true;
			}
		);
		$users = array(
			5 => $this->user( 5, array( Member::ROLE_PENDING ) ),
			6 => $this->user( 6, array( Member::ROLE ) ),
		);
		Functions\when( 'get_userdata' )->alias( static fn( $id ) => $users[ $id ] ?? false );
		Functions\when( 'get_user_meta' )->alias(
			static fn( $id, $key ) => array( 'billing_company' => 'Souza Arq', 'billing_cpf' => '52998224725', 'billing_phone' => '+5511987654321', 'billing_state' => 'SP', 'billing_city' => 'Campinas', 'reseller_ids' => '' )[ $key ] ?? ''
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function user( int $id, array $roles ): \WP_User {
		$user               = new \WP_User();
		$user->ID           = $id;
		$user->roles        = $roles;
		$user->display_name = 'Ana Souza';
		$user->user_email   = 'ana@escritorio.com.br';
		return $user;
	}

	/**
	 * Only these e-mails on (all are on by default).
	 *
	 * @param string ...$keys E-mails.
	 */
	private function enable( string ...$keys ): void {
		foreach ( array_keys( Notifications::EMAILS ) as $key ) {
			$this->settings[ 'email_' . $key . '_enabled' ] = in_array( $key, $keys, true );
		}
	}

	public function test_placeholders_and_saved_text_win_over_the_default(): void {
		$this->settings['email_member_pending_subject'] = 'Olá {first_name} ({company})';

		$email = Notifications::compose( 'member_pending', Notifications::member_vars( 5 ) );

		$this->assertSame( 'Olá Ana (Souza Arq)', $email['subject'] );
		$this->assertStringContainsString( 'Hello, Ana.', $email['text'], 'Empty body: the default.' );
		$this->assertStringContainsString( '<h1 class="aa-text" style="', $email['html'] );
		$this->assertStringContainsString( '<meta name="color-scheme" content="light dark">', $email['html'], 'The device picks light or dark.' );
		$this->assertStringContainsString( '>Application received</h1>', $email['html'] );
	}

	public function test_body_becomes_escaped_paragraphs_with_links(): void {
		$html = Notifications::body_html( "One <b>bold</b>\nline two\n\nSee https://axell.com.br/atelier/", array( 'p' => 'P', 'link' => 'L' ) );

		$this->assertSame( "<p class=\"aa-text\" style=\"P\">One &lt;b&gt;bold&lt;/b&gt;<br>\nline two</p>\n<p class=\"aa-text\" style=\"P\">See <a class=\"aa-link\" style=\"L\" href=\"https://axell.com.br/atelier/\">https://axell.com.br/atelier/</a></p>\n", $html );
	}

	public function test_pending_application_sends_team_and_pending(): void {
		$this->enable( 'team_new', 'member_pending', 'member_created' );

		Notifications::instance()->member_created( 5 );

		$this->assertCount( 2, $this->sent );
		$this->assertSame( array( 'admin@axell.com.br' ), $this->sent[0][0], 'No recipients saved: the admin e-mail.' );
		$this->assertSame( 'New membership application: Ana Souza', $this->sent[0][1] );
		$this->assertContains( 'Reply-To: Ana Souza <ana@escritorio.com.br>', $this->sent[0][3] );
		$this->assertContains( 'Content-Type: text/html; charset=UTF-8', $this->sent[0][3] );
		$this->assertStringContainsString( '529.982.247-25', $this->sent[0][2] );
		$this->assertSame( array( 'ana@escritorio.com.br' ), $this->sent[1][0] );
		$this->assertSame( 'We received your application, Ana', $this->sent[1][1] );
	}

	public function test_approved_application_sends_welcome_and_off_sends_nothing(): void {
		$this->enable( 'member_created' );
		$this->settings['email_team_new_to'] = 'curadoria@axell.com.br';

		Notifications::instance()->member_created( 6 );

		$this->assertCount( 1, $this->sent, 'The team e-mail is off.' );
		$this->assertSame( 'Welcome to Atelier Axell, Ana', $this->sent[0][1] );

		$this->enable();
		Notifications::instance()->member_created( 6 );
		Notifications::instance()->member_approved( 6 );
		$this->assertCount( 1, $this->sent, 'All off: nothing more.' );

		$this->settings = array();
		Notifications::instance()->member_approved( 6 );
		$this->assertCount( 2, $this->sent, 'Nothing saved: on by default.' );
	}

	public function test_atelier_name_by_default_or_as_saved(): void {
		$email = Notifications::compose( 'member_approved', Notifications::member_vars( 6 ) );
		$this->assertStringContainsString( 'you are now a member of Atelier Axell.', $email['text'] );
		$this->assertStringContainsString( 'target="_blank">Atelier Axell</a></p>', $email['html'], 'The footer.' );

		$this->settings['email_atelier_name'] = 'Atelier Axell Club';
		$email = Notifications::compose( 'member_approved', Notifications::member_vars( 6 ) );
		$this->assertSame( 'Your Atelier Axell Club membership is approved', $email['subject'] );
		$this->assertStringContainsString( 'target="_blank">Atelier Axell Club</a></p>', $email['html'] );
	}

	public function test_approval_sends_the_approved_e_mail(): void {
		$this->enable( 'member_approved' );

		Notifications::instance()->member_approved( 6 );

		$this->assertSame( 'Your Atelier Axell membership is approved', $this->sent[0][1] );
	}

	public function test_team_e_mail_has_type_document_label_and_uf_city(): void {
		$this->enable( 'team_new' );

		Notifications::instance()->member_created( 5 );

		$this->assertStringContainsString( 'Registration type: Individual · CPF', $this->sent[0][2] );
		$this->assertStringContainsString( 'CPF: 529.982.247-25', $this->sent[0][2] );
		$this->assertStringContainsString( 'City: SP Campinas', $this->sent[0][2] );
		$this->assertStringContainsString( "Landmark: <br>", $this->sent[0][2], 'An empty field stays empty.' );
		// The form's order: authorship, document, address, stores.
		$positions = array_map( fn( $label ) => strpos( $this->sent[0][2], $label ), array( 'Name:', 'Company:', 'E-mail:', 'Phone:', 'Main practice:', 'Registration type:', 'CPF:', 'Street:', 'Neighborhood:', 'City:', 'Postal code:', 'Partner stores:' ) );
		$sorted    = $positions;
		sort( $sorted );
		$this->assertSame( $sorted, $positions );
		$this->assertSame( 'CNPJ', Notifications::document_label( 'legal_entity' ) );
		$this->assertSame( 'CPF / CNPJ', Notifications::document_label( '' ) );
	}

	public function test_logo_none_site_and_custom(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 0 );
		Functions\when( 'wp_get_attachment_image_url' )->alias( static fn( $id ) => $id ? "https://axell.com.br/logo-$id.png" : false );
		Functions\when( 'get_post_mime_type' )->justReturn( 'image/png' );

		$this->assertSame( array( 'http://example.com/wp-content/plugins/axellcore-atelierclub/assets/email/atelier-axell-email.png', 'image/png' ), Notifications::logo(), 'By default the bundled logo, from the plugin when not in the library.' );
		Functions\when( 'get_posts' )->justReturn( array( 44 ) );
		$this->assertSame( 'https://axell.com.br/logo-44.png', Notifications::logo()[0], 'From the library once imported.' );
		$this->settings['email_logo'] = 'none';
		$this->assertNull( Notifications::logo() );
		$this->settings['email_logo'] = 'site';
		$this->assertNull( Notifications::logo(), 'The theme has no logo.' );
		Functions\when( 'get_theme_mod' )->justReturn( 12 );
		$this->assertSame( array( 'https://axell.com.br/logo-12.png', 'image/png' ), Notifications::logo() );
		$this->settings['email_logo']    = 'custom';
		$this->settings['email_logo_id'] = 30;
		$this->assertSame( 'https://axell.com.br/logo-30.png', Notifications::logo()[0] );

		$email = Notifications::compose( 'member_approved', Notifications::member_vars( 6 ) );
		$this->assertStringContainsString( '<img src="https://axell.com.br/logo-30.png" alt="Atelier Axell"', $email['html'] );
	}

	public function test_brand_in_text_by_default_or_as_saved(): void {
		$this->settings['email_logo'] = 'none';
		$this->assertSame( array( 'Atelier Axell', 'The Axell World' ), Notifications::brand() );
		$this->settings['email_atelier_name'] = 'Atelier Axell Club';
		$this->assertSame( 'Atelier Axell Club', Notifications::brand()[0], 'The brand in text is the Atelier name.' );
		unset( $this->settings['email_atelier_name'] );

		$this->settings['email_tagline'] = 'Universo próprio';
		$email = Notifications::compose( 'member_approved', Notifications::member_vars( 6 ) );

		$this->assertStringContainsString( '>Atelier Axell</a></p>', $email['html'] );
		$this->assertStringContainsString( '>Universo próprio</p>', $email['html'] );
		$this->assertStringNotContainsString( '<img', $email['html'] );
	}

	public function test_logo_width_by_default_or_as_set(): void {
		$this->assertSame( 140, Notifications::logo_width() );
		$email = Notifications::compose( 'member_approved', Notifications::member_vars( 6 ) );
		$this->assertStringContainsString( 'width="140" style="width:140px;', $email['html'] );

		$this->settings['email_logo_width'] = 220;
		$this->assertSame( 220, Notifications::logo_width() );
		$email = Notifications::compose( 'member_approved', Notifications::member_vars( 6 ) );
		$this->assertStringContainsString( 'width="220" style="width:220px;', $email['html'] );

		$this->settings['email_logo_width'] = 9000;
		$this->assertSame( 140, Notifications::logo_width(), 'Out of range: the default.' );
	}
}
