<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Member;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class MemberTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_roles_are_added_once_pending_without_capabilities(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_role' )->justReturn( null );
		$added = array();
		Functions\when( 'add_role' )->alias(
			static function ( $role, $label, $caps ) use ( &$added ) {
				$added[ $role ] = $caps;
				return null;
			}
		);

		Member::instance()->register_roles();

		$this->assertSame( array(), $added[ Member::ROLE_PENDING ] );
		$this->assertSame( array( 'read' => true ), $added[ Member::ROLE ] );
	}

	public function test_existing_roles_are_not_added_again(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_role' )->justReturn( new \stdClass() );
		Functions\when( 'wp_roles' )->justReturn(
			(object) array(
				'role_names' => array(
					Member::ROLE_PENDING => 'Pending Member',
					Member::ROLE         => 'Member',
				),
			)
		);
		Functions\expect( 'add_role' )->never();

		Member::instance()->register_roles();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * A user with these roles.
	 *
	 * @param string[] $roles Roles.
	 */
	private static function user( array $roles, int $id = 7 ): \WP_User {
		$user               = new \WP_User();
		$user->ID           = $id;
		$user->roles        = $roles;
		$user->display_name = 'Ana';
		return $user;
	}

	private function stub_links(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'admin_url' )->alias( static fn( $path ) => 'https://example.com/wp-admin/' . $path );
		Functions\when( 'add_query_arg' )->alias( static fn( $args, $url ) => $url . '?' . http_build_query( $args ) );
		Functions\when( 'wp_nonce_url' )->alias( static fn( $url, $action ) => $url . '&_wpnonce=' . $action );
	}

	public function test_a_pending_member_row_gets_approve(): void {
		$this->stub_links();
		Functions\when( 'current_user_can' )->justReturn( true );

		$actions = Member::instance()->row_actions( array( 'edit' => 'Edit' ), self::user( array( Member::ROLE_PENDING ) ) );

		$this->assertSame( array( 'edit', Member::APPROVE_ACTION ), array_keys( $actions ) );
		$this->assertStringContainsString( 'href="https://example.com/wp-admin/users.php?action=aa-approve&user=7&_wpnonce=aa-approve-user_7"', $actions[ Member::APPROVE_ACTION ] );
		$this->assertStringContainsString( 'aria-label="Approve Ana"', $actions[ Member::APPROVE_ACTION ] );
		$this->assertStringContainsString( '>Approve</a>', $actions[ Member::APPROVE_ACTION ] );
	}

	public function test_no_approve_for_members_or_without_permission(): void {
		$this->stub_links();
		Functions\when( 'current_user_can' )->justReturn( true );
		$this->assertSame( array(), Member::instance()->row_actions( array(), self::user( array( Member::ROLE ) ) ) );

		Functions\when( 'current_user_can' )->justReturn( false );
		$this->assertSame( array(), Member::instance()->row_actions( array(), self::user( array( Member::ROLE_PENDING ) ) ) );
	}

	public function test_bulk_approve_only_pending_members_one_can_promote(): void {
		$users = array(
			1 => self::user( array( Member::ROLE_PENDING ), 1 ),
			2 => self::user( array( Member::ROLE ), 2 ),
			3 => self::user( array( 'subscriber' ), 3 ),
			4 => self::user( array( Member::ROLE_PENDING ), 4 ),
		);
		Functions\when( 'get_userdata' )->alias( static fn( $id ) => $users[ $id ] ?? false );
		Functions\when( 'current_user_can' )->alias( static fn( $cap, $id = 0 ) => 4 !== $id );
		Functions\when( 'add_query_arg' )->alias( static fn( $key, $value, $url ) => $url . '?' . $key . '=' . $value );

		$redirect = Member::instance()->handle_bulk_approve( 'users.php', Member::APPROVE_ACTION, array( 1, 2, 3, 4 ) );

		$this->assertSame( 'users.php?aa-approved=1', $redirect );
		$this->assertSame( array( Member::ROLE ), $users[1]->roles );
		$this->assertSame( array( 'subscriber' ), $users[3]->roles, 'Only pending members.' );
		$this->assertSame( array( Member::ROLE_PENDING ), $users[4]->roles, 'Only those one can promote.' );
		$this->assertSame( 'x', Member::instance()->handle_bulk_approve( 'x', 'delete', array( 1 ) ), 'Other actions pass.' );
	}

	public function test_access_pending_never_members_only_when_allowed(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		$this->assertFalse( Member::can_access( self::user( array( Member::ROLE_PENDING ) ) ) );
		$this->assertFalse( Member::can_access( self::user( array( Member::ROLE ) ) ), 'Off by default.' );
		$this->assertTrue( Member::can_access( self::user( array( 'subscriber' ) ) ) );

		$admin          = self::user( array( Member::ROLE_PENDING, 'administrator' ) );
		$admin->allcaps = array( 'manage_options' => true );
		$this->assertTrue( Member::can_access( $admin ), 'Who manages the site always may.' );

		Functions\when( 'get_option' )->justReturn( array( 'members_can_log_in' => true ) );
		$this->assertTrue( Member::can_access( self::user( array( Member::ROLE ) ) ) );
		$this->assertFalse( Member::can_access( self::user( array( Member::ROLE_PENDING ) ) ), 'Pending never.' );
	}

	public function test_login_session_and_reset_are_refused_when_blocked(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( '__' )->returnArg( 1 );
		$pending = self::user( array( Member::ROLE_PENDING ), 5 );
		$member  = self::user( array( Member::ROLE ), 6 );
		Functions\when( 'get_userdata' )->alias( static fn( $id ) => 5 === $id ? $pending : $member );

		$this->assertSame( 'aa_member_pending', Member::instance()->authenticate( $pending )->get_error_code() );
		$this->assertSame( 'aa_member_no_access', Member::instance()->authenticate( $member )->get_error_code() );
		$error = new \WP_Error( 'incorrect_password', 'x' );
		$this->assertSame( $error, Member::instance()->authenticate( $error ), 'Earlier errors pass.' );

		$this->assertSame( 0, Member::instance()->current_user( 5 ) );
		$this->assertFalse( Member::instance()->allow_password_reset( true, 6 ) );
		$this->assertFalse( Member::instance()->application_passwords( true, $member ) );
	}

	public function test_blocked_users_have_no_reset_link(): void {
		$this->stub_links();
		Functions\when( 'current_user_can' )->justReturn( true );

		$actions = Member::instance()->row_actions( array( 'edit' => 'Edit', 'resetpassword' => 'Reset' ), self::user( array( Member::ROLE_PENDING ) ) );

		$this->assertSame( array( 'edit', Member::APPROVE_ACTION ), array_keys( $actions ) );
	}
}
