<?php
/**
 * The profiler clock.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Measures time without the profiler's own work.
 *
 * Profiler code adds the time it spends to the overhead. Clock::now() is
 * microtime( true ) minus that overhead. So any interval between two
 * Clock::now() values leaves out the profiler work that ran inside it.
 *
 * Every timer must read Clock::now(), never microtime( true ), so all times
 * in a report agree with each other.
 */
final class Clock {

	/**
	 * Profiler time spent so far in this request, in seconds.
	 *
	 * @var float
	 */
	private static $overhead = 0.0;

	/**
	 * Returns the current time without the profiler overhead so far.
	 *
	 * @return float Time in seconds, on the same scale as microtime( true ).
	 */
	public static function now(): float {
		return microtime( true ) - self::$overhead;
	}

	/**
	 * Adds profiler time to the overhead.
	 *
	 * @param float $seconds Time the profiler spent, in seconds.
	 */
	public static function add_overhead( float $seconds ): void {
		self::$overhead += $seconds;
	}

	/**
	 * Adds the time since a raw microtime( true ) value to the overhead.
	 *
	 * Call it at the end of profiler code, with the raw time from its start.
	 *
	 * @param float $raw_start Value of microtime( true ) when the profiler code started.
	 */
	public static function add_overhead_since( float $raw_start ): void {
		self::$overhead += microtime( true ) - $raw_start;
	}

	/**
	 * Returns the profiler time spent so far.
	 *
	 * @return float Overhead in seconds.
	 */
	public static function get_overhead(): float {
		return self::$overhead;
	}
}
