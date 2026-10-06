<?php
/**
 * @package Axellcore_Atelierclub\Tests
 */

declare( strict_types=1 );

namespace Axellcore_Atelierclub\Tests;

use Axellcore_Atelierclub\Template_Loader;
use Axellcore_Atelierclub\Plugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class TemplateLoaderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_template_registers_with_the_plugins_template_name(): void {
		Functions\when( 'file_exists' )->justReturn( true );
		Functions\when( 'file_get_contents' )->justReturn( "<!-- wp:post-content /-->\n" );
		Functions\when( '__' )->returnArg( 1 );

		Functions\expect( 'register_block_template' )
			->once()
			->with(
				Plugin::TEMPLATE_NAME,
				\Mockery::on(
					function ( $args ) {
						return isset( $args['content'] )
							&& false !== strpos( $args['content'], 'wp:post-content' )
							&& array( 'page' ) === $args['post_types'];
					}
				)
			);

		Template_Loader::instance()->register_template();

		$this->addToAssertionCount( 1 );
	}

	public function test_register_template_is_a_no_op_when_the_template_file_is_missing(): void {
		Functions\when( 'file_exists' )->justReturn( false );

		Functions\expect( 'register_block_template' )->never();

		Template_Loader::instance()->register_template();

		$this->addToAssertionCount( 1 );
	}


	public function test_enable_position_sticky_merges_position_sticky_setting(): void {
		$theme_json = \Mockery::mock( 'WP_Theme_JSON_Data' );
		$theme_json->shouldReceive( 'update_with' )
			->once()
			->with(
				\Mockery::on(
					function ( $data ) {
						return true === ( $data['settings']['position']['sticky'] ?? null );
					}
				)
			)
			->andReturn( 'merged' );

		$result = Template_Loader::instance()->enable_position_sticky( $theme_json );

		$this->assertSame( 'merged', $result );
	}

	/**
	 * Regression test for a real WP-core bug reproduced on this install:
	 * WP_Block_Templates_Registry::get_by_query() returns matches keyed by
	 * `plugin//slug` (string keys), and array_merge() preserves those —
	 * so get_block_templates() can come back as `['plugin//slug' => ...]`
	 * instead of `[0 => ...]` when only a plugin-registered template
	 * matches. Core's wp_get_post_content_block_attributes() then does
	 * `$current_template[0]->content` unconditionally and throws warnings.
	 */
	public function test_reindex_block_templates_restores_sequential_keys(): void {
		$template                                     = (object) array( 'slug' => 'atelier-club' );
		$keyed_by_plugin_slug                         = array( 'axellcore-atelierclub//atelier-club' => $template );
		$result                                        = Template_Loader::instance()->reindex_block_templates( $keyed_by_plugin_slug );

		$this->assertSame( array( $template ), $result );
		$this->assertArrayHasKey( 0, $result );
	}

	public function test_reindex_block_templates_passes_through_non_arrays_unchanged(): void {
		$this->assertNull( Template_Loader::instance()->reindex_block_templates( null ) );
	}
}
