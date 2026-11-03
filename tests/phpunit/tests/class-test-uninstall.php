<?php
/**
 * Tests for uninstall.php.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use WP_UnitTestCase;

use function LifecycleProfiler\add_saved_report;
use function LifecycleProfiler\get_loader_contents;
use function LifecycleProfiler\get_loader_path;
use function LifecycleProfiler\get_session_secret;
use const LifecycleProfiler\LEGACY_REPORT_OPTION;
use const LifecycleProfiler\LOADER_RETRY_TRANSIENT;
use const LifecycleProfiler\REPORT_INDEX_OPTION;

/**
 * Uninstall removes every option, the transient and the loader file.
 *
 * The test writes and removes the real loader file in mu-plugins. In wp-env,
 * that folder belongs to the tests site. The plugin writes the loader again on
 * the next admin page load.
 *
 * @coversNothing
 */
class Test_Uninstall extends WP_UnitTestCase {

	/**
	 * Adds plugin data to the current site.
	 *
	 * @return string ID of a saved report.
	 */
	private function add_site_data(): string {
		get_session_secret();
		update_option( LEGACY_REPORT_OPTION, array( 'meta' => array() ), false );
		set_transient( LOADER_RETRY_TRANSIENT, 1, HOUR_IN_SECONDS );

		// An option with the prefix that is missing from the index, as after a lost index update.
		add_option( 'lifecycle_profiler_report_0123456789ab', array(), '', false );

		return add_saved_report( array( 'meta' => array( 'url' => '/' ) ), microtime( true ) );
	}

	/**
	 * Returns the plugin option names of the current site.
	 *
	 * @return string[] Option names.
	 */
	private function get_plugin_options(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'lifecycle_profiler_' ) . '%' ) );
	}

	/**
	 * Runs a callback on a site. Switches sites only on multisite.
	 *
	 * @param int      $site_id  Site ID.
	 * @param callable $callback Callback to run.
	 */
	private function on_site( int $site_id, callable $callback ): void {
		if ( ! is_multisite() ) {
			$callback();
			return;
		}

		switch_to_blog( $site_id );
		$callback();
		restore_current_blog();
	}

	/**
	 * Uninstall leaves no options, reports, transient or loader file.
	 */
	public function test_uninstall_removes_all_data() {
		$site_ids = array( get_current_blog_id() );

		if ( is_multisite() ) {
			$site_ids[] = self::factory()->blog->create();
		}

		foreach ( $site_ids as $site_id ) {
			$this->on_site(
				$site_id,
				function () {
					$this->add_site_data();
					$this->assertGreaterThanOrEqual( 5, count( $this->get_plugin_options() ) );
				}
			);
		}

		file_put_contents( get_loader_path(), get_loader_contents() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		define( 'WP_UNINSTALL_PLUGIN', 'lifecycle-profiler/lifecycle-profiler.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core sets this constant before it runs uninstall.php.
		require dirname( __DIR__, 3 ) . '/uninstall.php';

		foreach ( $site_ids as $site_id ) {
			$this->on_site(
				$site_id,
				function () use ( $site_id ) {
					$this->assertSame( array(), $this->get_plugin_options(), "Site $site_id still has plugin options." );
					$this->assertFalse( get_option( REPORT_INDEX_OPTION ) );
					$this->assertFalse( get_transient( LOADER_RETRY_TRANSIENT ) );
				}
			);
		}

		$this->assertFileDoesNotExist( get_loader_path() );
	}
}
