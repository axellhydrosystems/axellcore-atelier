<?php
/**
 * @package Axellcore_Atelier\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelier\Tests;

use Axellcore_Atelier\Cache;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'absint' )->alias( 'intval' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_without_cache_plugins_only_the_own_action_fires(): void {
		Actions\expectDone( 'axellcore_atelier_purge_cache' )->once();
		Actions\expectDone( 'litespeed_purge_all' )->never();

		Cache::purge();

		$this->addToAssertionCount( 1 );
	}

	public function test_an_active_cache_plugin_is_purged_once(): void {
		Functions\expect( 'wp_cache_clear_cache' )->once();

		Cache::purge();

		$this->addToAssertionCount( 1 );
	}

	public function test_an_action_based_cache_is_purged_when_hooked(): void {
		add_action( 'wpfc_clear_all_cache', '__return_true' );
		Actions\expectDone( 'wpfc_clear_all_cache' )->once();

		Cache::purge();

		$this->addToAssertionCount( 1 );
	}

	public function test_a_new_version_purges_once_and_is_remembered(): void {
		Functions\when( 'get_option' )->justReturn( '0.0.0-old' );
		Functions\expect( 'update_option' )->once()->with( Cache::VERSION_OPTION, AXELLCORE_ATELIER_VERSION );
		Actions\expectDone( 'axellcore_atelier_purge_cache' )->once();

		Cache::purge_after_update();

		$this->addToAssertionCount( 1 );
	}

	public function test_the_same_version_does_not_purge(): void {
		Functions\when( 'get_option' )->justReturn( AXELLCORE_ATELIER_VERSION );
		Actions\expectDone( 'axellcore_atelier_purge_cache' )->never();

		Cache::purge_after_update();

		$this->addToAssertionCount( 1 );
	}

	public function test_only_a_different_atelier_page_purges(): void {
		Actions\expectDone( 'axellcore_atelier_purge_cache' )->once();

		Cache::purge_on_new_page( array( 'page_id' => 5, 'pending_on_create' => true ), array( 'page_id' => 5, 'pending_on_create' => false ) );
		Cache::purge_on_new_page( array( 'page_id' => 5 ), array( 'page_id' => 9 ) );

		$this->addToAssertionCount( 1 );
	}
}
