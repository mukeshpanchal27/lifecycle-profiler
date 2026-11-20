<?php
/**
 * Tests for saving reports.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use WP_UnitTestCase;

use function LifecycleProfiler\get_total_time;

/**
 * Tests for includes/storage.php.
 *
 * @covers ::LifecycleProfiler\get_total_time
 */
class Test_Storage extends WP_UnitTestCase {

	/**
	 * Loads the files. Only the mu-plugin loader loads them outside tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once dirname( __DIR__, 3 ) . '/includes/class-clock.php';
		require_once dirname( __DIR__, 3 ) . '/includes/report.php';
		require_once dirname( __DIR__, 3 ) . '/includes/storage.php';
	}

	/**
	 * The total ends where the shutdown phase ends, so the phases add up to it.
	 */
	public function test_total_ends_with_shutdown_phase() {
		$report = array(
			'phases' => array(
				'bootstrap' => array(
					'start'    => 0.0,
					'duration' => 10.5,
				),
				'shutdown'  => array(
					'start'    => 40.25,
					'duration' => 2.125,
				),
			),
		);

		$this->assertSame( 42.375, get_total_time( $report, 50.0 ) );
	}

	/**
	 * Without a shutdown phase, the total is the time given.
	 */
	public function test_total_without_shutdown_phase() {
		$this->assertSame( 50.0, get_total_time( array( 'phases' => array() ), 50.0 ) );
		$this->assertSame( 50.0, get_total_time( array(), 50.0 ) );
	}
}
