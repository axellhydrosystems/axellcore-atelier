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

	public function test_register_post_type_registers_aa_member_as_non_public(): void {
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'register_post_type' )
			->once()
			->with(
				Member::POST_TYPE,
				\Mockery::on(
					function ( $args ) {
						return false === $args['public']
							&& true === $args['show_ui']
							&& array( 'title' ) === $args['supports'];
					}
				)
			);

		Member::instance()->register_post_type();

		$this->addToAssertionCount( 1 );
	}
}
