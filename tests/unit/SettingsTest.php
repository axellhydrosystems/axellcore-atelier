<?php
/**
 * @package Axellcore_Atelier\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelier\Tests;

use Axellcore_Atelier\Settings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'absint' )->alias( 'intval' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_page_is_the_chosen_one(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->alias(
			static function ( $id ) {
				return 12 === (int) $id ? new \WP_Post( array( 'ID' => 12, 'post_name' => 'clube' ) ) : null;
			}
		);
		Functions\expect( 'get_page_by_path' )->never();

		$this->assertSame( 12, Settings::page()->ID );
	}

	public function test_without_a_choice_there_is_no_page(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 0 ) );
		// get_post( 0 ) would return the post being viewed; no guess by slug either.
		Functions\expect( 'get_post' )->never();
		Functions\expect( 'get_page_by_path' )->never();

		$this->assertNull( Settings::page() );
		$this->assertFalse( Settings::never_configured() );
	}

	public function test_never_configured_until_the_page_setting_exists(): void {
		Functions\when( 'get_option' )->justReturn( false );
		$this->assertTrue( Settings::never_configured() );

		Functions\when( 'get_option' )->justReturn( array( 'pending_on_create' => true ) );
		$this->assertTrue( Settings::never_configured() );
		$this->assertNull( Settings::page() );
	}

	public function test_a_trashed_choice_is_no_page(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->justReturn( new \WP_Post( array( 'ID' => 12, 'post_status' => 'trash' ) ) );
		Functions\expect( 'get_page_by_path' )->never();

		$this->assertNull( Settings::page() );
	}

	public function test_post_state_marks_only_the_atelier_page(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->alias(
			static function ( $post ) {
				if ( $post instanceof \WP_Post ) {
					return $post;
				}
				return 12 === (int) $post ? new \WP_Post( array( 'ID' => 12 ) ) : null;
			}
		);
		$front = array( 'page_on_front' => 'Front Page' );

		$this->assertSame( $front + array( 'axellcore_atelier_page' => 'Atelier Page' ), Settings::instance()->post_state( $front, new \WP_Post( array( 'ID' => 12 ) ) ) );
		$this->assertSame( $front, Settings::instance()->post_state( $front, new \WP_Post( array( 'ID' => 13 ) ) ) );
	}

	public function test_is_page(): void {
		Functions\when( 'get_option' )->justReturn( array( 'page_id' => 12 ) );
		Functions\when( 'get_post' )->alias(
			static function ( $post ) {
				if ( $post instanceof \WP_Post ) {
					return $post;
				}
				return 12 === (int) $post ? new \WP_Post( array( 'ID' => 12 ) ) : null;
			}
		);

		$this->assertTrue( Settings::is_page( 12 ) );
		$this->assertFalse( Settings::is_page( new \WP_Post( array( 'ID' => 13 ) ) ) );
		$this->assertFalse( Settings::is_page( 0 ) );
	}

	public function test_sanitize_keeps_the_access_setting_as_a_boolean(): void {
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		Functions\when( 'get_option' )->justReturn( array() );

		$on  = Settings::instance()->sanitize( array( 'members_can_log_in' => '1', 'other' => 'x' ) );
		$off = Settings::instance()->sanitize( array( 'members_can_log_in' => '0' ) );

		$this->assertTrue( $on['members_can_log_in'] );
		$this->assertFalse( $off['members_can_log_in'] );
		$this->assertArrayNotHasKey( 'other', $on );
		$this->assertFalse( Settings::DEFAULTS['members_can_log_in'], 'Off by default.' );
	}

	private function stub_texts( array $saved ): void {
		Functions\when( 'get_option' )->justReturn( $saved );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( '_x' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
		Functions\when( 'is_email' )->alias( static fn( $e ) => false !== strpos( (string) $e, '@' ) );
		Functions\when( 'sanitize_email' )->returnArg( 1 );
		Functions\when( 'add_settings_error' )->justReturn( null );
	}

	public function test_a_text_equal_to_the_default_is_not_stored(): void {
		$this->stub_texts( array() );
		$defaults = \Axellcore_Atelier\Notifications::defaults( 'member_pending' );

		$values = Settings::instance()->sanitize(
			array(
				'email_member_pending_enabled' => '1',
				'email_member_pending_subject' => $defaults['subject'],
				'email_member_pending_heading' => 'My own heading',
				'email_member_pending_body'    => str_replace( "\n", "\r\n", $defaults['body'] ) . "\r\n",
			)
		);

		$this->assertTrue( $values['email_member_pending_enabled'] );
		$this->assertSame( '', $values['email_member_pending_subject'], 'Equal to the default: not stored.' );
		$this->assertSame( 'My own heading', $values['email_member_pending_heading'] );
		$this->assertSame( '', $values['email_member_pending_body'], 'Line endings and ends do not count.' );
	}

	public function test_saving_one_tab_keeps_the_others(): void {
		$this->stub_texts(
			array(
				'page_id'                   => 7,
				'email_team_new_enabled'    => true,
				'email_team_new_subject'    => 'Custom',
				'members_can_log_in'        => true,
			)
		);

		$values = Settings::instance()->sanitize( array( 'pending_on_create' => '0', 'members_can_log_in' => '0' ) );

		$this->assertFalse( $values['pending_on_create'] );
		$this->assertFalse( $values['members_can_log_in'] );
		$this->assertSame( 7, $values['page_id'] );
		$this->assertTrue( $values['email_team_new_enabled'] );
		$this->assertSame( 'Custom', $values['email_team_new_subject'] );
	}

	public function test_invalid_recipients_are_dropped(): void {
		$this->stub_texts( array() );

		$values = Settings::instance()->sanitize( array( 'email_team_new_to' => 'curadoria@axell.com.br, nope, ana@x.com, curadoria@axell.com.br' ) );

		$this->assertSame( 'curadoria@axell.com.br, ana@x.com', $values['email_team_new_to'] );
	}

	public function test_email_logo_and_brand(): void {
		$this->stub_texts( array() );
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( 'wp_attachment_is_image' )->alias( static fn( $id ) => 30 === $id );

		Functions\when( 'get_posts' )->justReturn( array( 30 ) );

		$values = Settings::instance()->sanitize( array( 'email_logo' => 'nope', 'email_logo_id' => '99', 'email_tagline' => 'Outro' ) );

		$this->assertSame( '', $values['email_logo'], 'The default choice (the bundled logo) is not stored.' );
		$this->assertSame( 'none', Settings::instance()->sanitize( array( 'email_logo' => 'none' ) )['email_logo'] );
		$this->assertSame( 0, $values['email_logo_id'], 'Not an image.' );
		$this->assertSame( 'Outro', $values['email_tagline'] );
		$this->assertSame( '', Settings::instance()->sanitize( array( 'email_atelier_name' => 'Atelier Axell' ) )['email_atelier_name'], 'The default is not stored.' );
		$this->assertSame( 'Atelier Lumière', Settings::instance()->sanitize( array( 'email_atelier_name' => 'Atelier Lumière' ) )['email_atelier_name'] );
		$this->assertSame( 0, Settings::instance()->sanitize( array( 'email_logo_id' => '30' ) )['email_logo_id'], 'The bundled logo is the default: not stored.' );
		Functions\when( 'wp_attachment_is_image' )->justReturn( true );
		$this->assertSame( 31, Settings::instance()->sanitize( array( 'email_logo_id' => '31' ) )['email_logo_id'] );
	}

	public function test_logo_width_is_stored_only_when_filled(): void {
		$this->stub_texts( array() );
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );

		$this->assertSame( '', Settings::instance()->sanitize( array( 'email_logo_width' => '' ) )['email_logo_width'] );
		$this->assertSame( 220, Settings::instance()->sanitize( array( 'email_logo_width' => '220' ) )['email_logo_width'] );
		$this->assertSame( 600, Settings::instance()->sanitize( array( 'email_logo_width' => '9000' ) )['email_logo_width'], 'At most the e-mail width.' );
		$this->assertSame( 40, Settings::instance()->sanitize( array( 'email_logo_width' => '5' ) )['email_logo_width'] );
	}

	public function test_recaptcha_settings(): void {
		$this->stub_texts( array( 'email_team_new_subject' => 'Custom' ) );

		$values = Settings::instance()->sanitize(
			array(
				'recaptcha_enabled'       => '1',
				'recaptcha_skip_local'    => '0',
				'recaptcha_version'       => 'v9',
				'recaptcha_v3_site_key'   => '  site  ',
				'recaptcha_v3_threshold'  => '0,7',
			)
		);
		$this->assertTrue( $values['recaptcha_enabled'] );
		$this->assertFalse( $values['recaptcha_skip_local'] );
		$this->assertSame( 'v3', $values['recaptcha_version'], 'Only v2 or v3.' );
		$this->assertSame( 'site', $values['recaptcha_v3_site_key'] );
		$this->assertSame( 0.7, $values['recaptcha_v3_threshold'], 'A comma counts as the decimal point.' );
		$this->assertSame( 'Custom', $values['email_team_new_subject'], 'Other tabs kept.' );

		$this->assertSame( 'v2', Settings::instance()->sanitize( array( 'recaptcha_version' => 'v2' ) )['recaptcha_version'] );
		$this->assertSame( 1.0, Settings::instance()->sanitize( array( 'recaptcha_v3_threshold' => '1.5' ) )['recaptcha_v3_threshold'] );
		$this->assertSame( '', Settings::instance()->sanitize( array( 'recaptcha_v3_threshold' => '' ) )['recaptcha_v3_threshold'], 'Empty: the default.' );
		$this->assertTrue( Settings::DEFAULTS['recaptcha_skip_local'], 'Skips local addresses by default.' );
		$this->assertTrue( Settings::DEFAULTS['recaptcha_enabled'], 'On by default (it needs the keys).' );
	}

	public function test_places_settings(): void {
		$this->stub_texts( array( 'recaptcha_v3_site_key' => 'site' ) );

		$values = Settings::instance()->sanitize( array( 'places_enabled' => '0', 'places_api_key' => '  key-123  ' ) );

		$this->assertFalse( $values['places_enabled'] );
		$this->assertSame( 'key-123', $values['places_api_key'] );
		$this->assertSame( 'site', $values['recaptcha_v3_site_key'], 'The reCAPTCHA keys are kept.' );
		$this->assertTrue( Settings::DEFAULTS['places_enabled'], 'On by default (it needs the key).' );
	}
}
