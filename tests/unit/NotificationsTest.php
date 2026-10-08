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
			static fn( $id, $key ) => array( 'billing_company' => 'Souza Arq', 'billing_cpf' => '52998224725', 'billing_phone' => '+5511987654321', 'reseller_ids' => '' )[ $key ] ?? ''
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

	private function enable( string ...$keys ): void {
		foreach ( $keys as $key ) {
			$this->settings[ 'email_' . $key . '_enabled' ] = true;
		}
	}

	public function test_placeholders_and_saved_text_win_over_the_default(): void {
		$this->settings['email_member_pending_subject'] = 'Olá {first_name} ({company})';

		$email = Notifications::compose( 'member_pending', Notifications::member_vars( 5 ) );

		$this->assertSame( 'Olá Ana (Souza Arq)', $email['subject'] );
		$this->assertStringContainsString( 'Hello, Ana.', $email['text'], 'Empty body: the default.' );
		$this->assertStringContainsString( '<h1 style="', $email['html'] );
		$this->assertStringContainsString( '>Application received</h1>', $email['html'] );
	}

	public function test_body_becomes_escaped_paragraphs_with_links(): void {
		$html = Notifications::body_html( "One <b>bold</b>\nline two\n\nSee https://axell.com.br/atelier/", array( 'p' => 'P', 'link' => 'L' ) );

		$this->assertSame( "<p style=\"P\">One &lt;b&gt;bold&lt;/b&gt;<br>\nline two</p>\n<p style=\"P\">See <a style=\"L\" href=\"https://axell.com.br/atelier/\">https://axell.com.br/atelier/</a></p>\n", $html );
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
		$this->assertSame( 'Welcome to the Atelier Axell Club, Ana', $this->sent[0][1] );

		$this->settings = array();
		Notifications::instance()->member_created( 6 );
		Notifications::instance()->member_approved( 6 );
		$this->assertCount( 1, $this->sent, 'All off: nothing more.' );
	}

	public function test_approval_sends_the_approved_e_mail(): void {
		$this->enable( 'member_approved' );

		Notifications::instance()->member_approved( 6 );

		$this->assertSame( 'Your Atelier Axell Club membership is approved', $this->sent[0][1] );
	}
}
