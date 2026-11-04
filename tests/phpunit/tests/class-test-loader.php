<?php
/**
 * Tests for the mu-plugin loader file.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use WP_UnitTestCase;

use function LifecycleProfiler\get_loader_contents;
use function LifecycleProfiler\get_loader_path;
use function LifecycleProfiler\is_loader_current;
use function LifecycleProfiler\maybe_install_loader;
use const LifecycleProfiler\LOADER_RETRY_TRANSIENT;

/**
 * Tests for includes/loader.php.
 *
 * These tests write the real loader file in mu-plugins. In wp-env, that
 * folder belongs to the tests site. Each test leaves a current loader behind.
 *
 * @covers ::LifecycleProfiler\get_loader_contents
 * @covers ::LifecycleProfiler\is_loader_current
 * @covers ::LifecycleProfiler\maybe_install_loader
 */
class Test_Loader extends WP_UnitTestCase {

	/**
	 * An administrator.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * Creates the administrator.
	 *
	 * @param \WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( is_multisite() ) {
			grant_super_admin( self::$admin_id );
		}
	}

	/**
	 * Writes the loader file with the given contents.
	 *
	 * @param string $contents File contents.
	 */
	private function write_loader( string $contents ): void {
		wp_mkdir_p( dirname( get_loader_path() ) );
		file_put_contents( get_loader_path(), $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Leaves a current loader behind for the tests site.
	 */
	public function tear_down() {
		$this->write_loader( get_loader_contents() );

		parent::tear_down();
	}

	/**
	 * The loader stops when it is opened outside WordPress.
	 */
	public function test_loader_has_direct_access_guard() {
		$this->assertStringContainsString( "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}", get_loader_contents() );
	}

	/**
	 * The loader is valid PHP.
	 */
	public function test_loader_is_valid_php() {
		$this->assertIsArray( token_get_all( get_loader_contents(), TOKEN_PARSE ) );
	}

	/**
	 * Only a loader with the current contents counts as current.
	 */
	public function test_loader_current_check() {
		$this->write_loader( get_loader_contents() );
		$this->assertTrue( is_loader_current() );

		$this->write_loader( "<?php\n// An older loader.\n" );
		$this->assertFalse( is_loader_current() );
	}

	/**
	 * An admin page load replaces an outdated loader.
	 */
	public function test_outdated_loader_is_rewritten() {
		delete_transient( LOADER_RETRY_TRANSIENT );
		$this->write_loader( "<?php\n// An older loader.\n" );
		wp_set_current_user( self::$admin_id );

		maybe_install_loader();

		$this->assertTrue( is_loader_current() );
	}

	/**
	 * Users who cannot activate plugins do not change the loader.
	 */
	public function test_loader_not_rewritten_without_capability() {
		delete_transient( LOADER_RETRY_TRANSIENT );
		$this->write_loader( "<?php\n// An older loader.\n" );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		maybe_install_loader();

		$this->assertFalse( is_loader_current() );
	}
}
