<?php
/**
 * @package Axellcore_Atelier\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelier\Tests;

use Axellcore_Atelier\Enhanced_Select;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class EnhancedSelectTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_directives_keep_their_store(): void {
		$this->assertSame( 'axell/address::actions.onState', Enhanced_Select::with_namespace( 'actions.onState', 'axell/address' ) );
		$this->assertSame( 'axell/address::!state.hasStateList', Enhanced_Select::with_namespace( '!state.hasStateList', 'axell/address' ), 'The namespace before the negation.' );
		$this->assertSame( 'other/store::state.x', Enhanced_Select::with_namespace( 'other/store::state.x', 'axell/address' ), 'A namespace already there stays.' );
		$this->assertSame( '', Enhanced_Select::with_namespace( '', 'axell/address' ) );
	}

	public function test_fields_by_default(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		$fields = Enhanced_Select::fields();

		$this->assertTrue( $fields['primary_focus']['search'] );
		$this->assertFalse( $fields['profile_type']['search'], 'The registration type has no filter.' );
		$this->assertSame( 'uf', $fields['state']['kind'] );
	}

	public function test_wrap_in_touches_only_the_select(): void {
		$html = '<div class="x"><select name="a"><option>1</option></select></div><p>after</p>';
		$this->assertSame( 'no select here', Enhanced_Select::wrap_in( 'no select here', array() ) );
		$this->assertStringEndsWith( '</div><p>after</p>', str_replace( '<select', '', $html ) );
	}
}
