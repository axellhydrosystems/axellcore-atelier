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
					Member::ROLE_PENDING => 'Membro Pendente',
					Member::ROLE         => 'Membro',
				),
			)
		);
		Functions\expect( 'add_role' )->never();

		Member::instance()->register_roles();

		$this->addToAssertionCount( 1 );
	}
}
