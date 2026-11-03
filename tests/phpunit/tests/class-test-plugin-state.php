<?php
/**
 * Tests for the plugin active check.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use WP_UnitTestCase;

use function LifecycleProfiler\get_plugin_basename;
use function LifecycleProfiler\is_plugin_enabled;

/**
 * Tests for includes/plugin-state.php.
 *
 * @covers ::LifecycleProfiler\is_plugin_enabled
 * @covers ::LifecycleProfiler\get_plugin_basename
 */
class Test_Plugin_State extends WP_UnitTestCase {

	/**
	 * Loads the file. The test install does not use the mu-plugin loader.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once dirname( __DIR__, 3 ) . '/includes/plugin-state.php';
	}

	/**
	 * The basename names the main plugin file in the plugin folder.
	 */
	public function test_basename_names_the_main_plugin_file() {
		$this->assertSame( basename( dirname( __DIR__, 3 ) ) . '/lifecycle-profiler.php', get_plugin_basename() );
	}

	/**
	 * The plugin is enabled when it is in active_plugins.
	 */
	public function test_enabled_when_active_on_the_site() {
		update_option( 'active_plugins', array( get_plugin_basename() ) );

		$this->assertTrue( is_plugin_enabled() );
	}

	/**
	 * The plugin is not enabled when it is in neither option.
	 */
	public function test_not_enabled_when_inactive() {
		update_option( 'active_plugins', array( 'hello.php' ) );

		if ( is_multisite() ) {
			update_site_option( 'active_sitewide_plugins', array() );
		}

		$this->assertFalse( is_plugin_enabled() );
	}

	/**
	 * On multisite, network activation also counts.
	 *
	 * @group ms-required
	 */
	public function test_enabled_when_network_active() {
		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array( get_plugin_basename() => time() ) );

		$this->assertTrue( is_plugin_enabled() );
	}

	/**
	 * On a single site, active_sitewide_plugins is ignored.
	 *
	 * @group ms-excluded
	 */
	public function test_network_option_ignored_on_single_site() {
		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array( get_plugin_basename() => time() ) );

		$this->assertFalse( is_plugin_enabled() );
	}
}
