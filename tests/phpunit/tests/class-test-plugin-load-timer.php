<?php
/**
 * Tests for the plugin load timer.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use LifecycleProfiler\Plugin_Load_Timer;
use WP_UnitTestCase;

use function LifecycleProfiler\get_report;

/**
 * Tests for includes/class-plugin-load-timer.php.
 *
 * @covers \LifecycleProfiler\Plugin_Load_Timer
 */
class Test_Plugin_Load_Timer extends WP_UnitTestCase {

	/**
	 * The timer under test.
	 *
	 * @var Plugin_Load_Timer
	 */
	private $timer;

	/**
	 * Loads the files. Only the mu-plugin loader loads them outside tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once dirname( __DIR__, 3 ) . '/includes/class-clock.php';
		require_once dirname( __DIR__, 3 ) . '/includes/report.php';
		require_once dirname( __DIR__, 3 ) . '/includes/class-plugin-load-timer.php';
	}

	/**
	 * Starts a timer.
	 */
	public function set_up() {
		parent::set_up();

		$this->timer = new Plugin_Load_Timer();
		$this->timer->register();
	}

	/**
	 * Removes the timer's hooks.
	 */
	public function tear_down() {
		$this->timer->unregister();

		parent::tear_down();
	}

	/**
	 * Returns the saved load time of a plugin.
	 *
	 * @param string $file Plugin file.
	 * @return float Duration in milliseconds.
	 */
	private function load_time( string $file ): float {
		return (float) get_report()['plugins'][ plugin_basename( $file ) ]['duration'];
	}

	/**
	 * A read of active_plugins outside core does not move the start.
	 *
	 * For example, an mu-plugin checks whether another plugin is active.
	 */
	public function test_other_read_does_not_move_start() {
		usleep( 20000 );
		get_option( 'active_plugins' );

		$file = WP_PLUGIN_DIR . '/lp-test-other-read/plugin.php';
		do_action( 'plugin_loaded', $file );

		$this->assertGreaterThanOrEqual( 20.0, $this->load_time( $file ) );
	}

	/**
	 * The read by core, just before it loads plugins, moves the start.
	 */
	public function test_core_read_moves_start() {
		usleep( 20000 );
		wp_get_active_and_valid_plugins();

		$file = WP_PLUGIN_DIR . '/lp-test-core-read/plugin.php';
		do_action( 'plugin_loaded', $file );

		$this->assertLessThan( 20.0, $this->load_time( $file ) );
	}
}
