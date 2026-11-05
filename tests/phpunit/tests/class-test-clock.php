<?php
/**
 * Tests for the profiler clock.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use LifecycleProfiler\Clock;
use LifecycleProfiler\Timed_Callback;
use WP_UnitTestCase;

use function LifecycleProfiler\get_overhead_metric;

/**
 * Tests for includes/class-clock.php and its use in Timed_Callback.
 *
 * @covers \LifecycleProfiler\Clock
 * @covers \LifecycleProfiler\Timed_Callback
 * @covers ::LifecycleProfiler\get_overhead_metric
 */
class Test_Clock extends WP_UnitTestCase {

	/**
	 * Loads the files. Only the mu-plugin loader loads them outside tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once dirname( __DIR__, 3 ) . '/includes/class-clock.php';
		require_once dirname( __DIR__, 3 ) . '/includes/class-timed-callback.php';
		require_once dirname( __DIR__, 3 ) . '/includes/server-timing.php';
	}

	/**
	 * The clock leaves out the overhead added so far.
	 */
	public function test_now_leaves_out_overhead() {
		$before = microtime( true ) - Clock::now();

		Clock::add_overhead( 0.25 );

		$after = microtime( true ) - Clock::now();

		$this->assertEqualsWithDelta( 0.25, $after - $before, 0.001 );
	}

	/**
	 * An interval leaves out the profiler time inside it, and only that.
	 */
	public function test_interval_leaves_out_overhead_inside_it() {
		$start = Clock::now();
		usleep( 20000 );
		Clock::add_overhead( 0.010 );
		$elapsed = Clock::now() - $start;

		$this->assertGreaterThan( 0.008, $elapsed, 'The 20 ms sleep must stay in the interval.' );
		$this->assertLessThan( 0.020, $elapsed, 'The 10 ms of overhead must be left out.' );
	}

	/**
	 * Overhead added since a raw time is the time that passed.
	 */
	public function test_add_overhead_since() {
		$overhead = Clock::get_overhead();
		$raw      = microtime( true );
		usleep( 5000 );
		Clock::add_overhead_since( $raw );

		$this->assertGreaterThanOrEqual( 0.005, Clock::get_overhead() - $overhead );
	}

	/**
	 * A parent callback leaves out the wrapper time of its children.
	 */
	public function test_parent_leaves_out_child_wrapper_time() {
		$child  = new Timed_Callback( '__return_null' );
		$parent = new Timed_Callback(
			static function () use ( $child ) {
				for ( $i = 0; $i < 500; $i++ ) {
					$child( null );
				}
			}
		);

		$overhead = Clock::get_overhead();
		$raw      = microtime( true );
		$parent();
		$raw_elapsed = microtime( true ) - $raw;
		$inside      = Clock::get_overhead() - $overhead;

		$this->assertGreaterThan( 0.0, $inside, 'The child calls must add overhead.' );
		$this->assertLessThan( $raw_elapsed, $parent->get_total_time(), 'The parent time must be less than the raw time.' );
		$this->assertEqualsWithDelta( $child->get_total_time(), $parent->get_total_time() - $parent->get_self_time(), 1e-9, 'The nested time must be the time of the children.' );
		$this->assertGreaterThanOrEqual( 0.0, $parent->get_self_time() );
		$this->assertSame( 500, $child->get_calls() );
	}

	/**
	 * The overhead metric reports milliseconds with three decimals.
	 */
	public function test_overhead_metric() {
		$this->assertSame( 'lp-overhead;dur=6.120;desc="Profiler time left out"', get_overhead_metric( 0.00612 ) );
	}
}
