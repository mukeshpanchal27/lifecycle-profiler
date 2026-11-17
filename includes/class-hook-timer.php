<?php
/**
 * Times every hook callback during a session.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps hook callbacks in Timed_Callback, and adds their times to the report.
 *
 * WordPress fires the "all" hook right before it runs the callbacks of any
 * hook. At that point this class wraps the callbacks of that hook. Only hooks
 * that fire are wrapped, and callbacks added later are wrapped the next time
 * their hook fires.
 *
 * Each callback keeps its key in WP_Hook, so remove_action() and has_filter()
 * with the original callable keep working.
 */
final class Hook_Timer {

	/**
	 * Report section for callback times.
	 */
	const SECTION = 'callbacks';

	/**
	 * Every wrapper created, with where it sits.
	 *
	 * @var array<int, array{hook: string, priority: int, id: string, timer: Timed_Callback}>
	 */
	private $wrapped = array();

	/**
	 * Cost of WordPress calling wrap_callbacks() through the "all" hook, in seconds.
	 *
	 * This happens before wrap_callbacks() starts, so the method cannot time
	 * it. calibrate() measures it once per request.
	 *
	 * @var float
	 */
	private $dispatch_cost = 0.0;

	/**
	 * Number of runs in each calibration loop.
	 */
	const CALIBRATION_RUNS = 500;

	/**
	 * Adds the hooks. Call this from the mu-plugin loader.
	 */
	public function register(): void {
		$this->calibrate();

		add_action( 'all', array( $this, 'wrap_callbacks' ), PHP_INT_MIN );

		// Before the report is saved at PHP_INT_MAX.
		add_action( 'shutdown', array( $this, 'record_callbacks' ), PHP_INT_MAX - 10, 0 );
	}

	/**
	 * Wraps the callbacks of the hook that is about to run.
	 *
	 * @param string $hook_name Name of the hook about to run.
	 */
	public function wrap_callbacks( $hook_name ): void {
		$raw_start = microtime( true );

		try {
			global $wp_filter;

			if ( 'all' === $hook_name || ! isset( $wp_filter[ $hook_name ] ) ) {
				return;
			}

			$hook = $wp_filter[ $hook_name ];

			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $callback ) {
					if ( $callback['function'] instanceof Timed_Callback || is_own_callback( $callback['function'] ) ) {
						continue;
					}

					$timer = new Timed_Callback( $callback['function'] );

					$hook->callbacks[ $priority ][ $id ]['function'] = $timer;

					$this->wrapped[] = array(
						'hook'     => (string) $hook_name,
						'priority' => (int) $priority,
						'id'       => (string) $id,
						'timer'    => $timer,
					);
				}
			}
		} finally {
			Clock::add_overhead( microtime( true ) - $raw_start + $this->dispatch_cost );
		}
	}

	/**
	 * Measures the profiler costs that happen outside its own methods.
	 *
	 * Two costs cannot be timed where they happen: WordPress calling
	 * wrap_callbacks() for every hook, and WordPress calling each wrapper.
	 * This method times both in short loops, so the profiler can add them to
	 * the overhead each time they happen. The loops take about 1 ms, and that
	 * time is profiler time too.
	 */
	private function calibrate(): void {
		$raw_start = microtime( true );
		$overhead  = Clock::get_overhead();
		$args      = array( null );
		$timer     = new Timed_Callback( '__return_null' );

		$fire         = static function (): void {
			do_action( 'lifecycle_profiler_calibration' );
		};
		$call_plain   = static function () use ( $args ): void {
			call_user_func_array( '__return_null', $args );
		};
		$call_wrapped = static function () use ( $timer, $args ): void {
			call_user_func_array( $timer, $args );
		};

		$plain = $this->time_runs( $fire );

		add_action( 'all', array( $this, 'wrap_callbacks' ), PHP_INT_MIN );
		$with_all = $this->time_runs( $fire );
		remove_action( 'all', array( $this, 'wrap_callbacks' ), PHP_INT_MIN );

		$this->dispatch_cost = max( 0.0, ( $with_all - $plain ) / self::CALIBRATION_RUNS );

		$direct  = $this->time_runs( $call_plain );
		$wrapped = $this->time_runs( $call_wrapped );

		Timed_Callback::set_call_cost( ( $wrapped - $direct ) / self::CALIBRATION_RUNS );

		// Count the calibration as profiler time, without the parts the loops already counted.
		Clock::add_overhead( microtime( true ) - $raw_start - ( Clock::get_overhead() - $overhead ) );
	}

	/**
	 * Times a loop of calibration runs.
	 *
	 * Runs the loop once to warm up, then twice, and keeps the faster time.
	 * Profiler time that the runs add to the overhead is taken out, so only
	 * the cost that the profiler cannot see is left.
	 *
	 * @param callable $run One run.
	 * @return float Time of CALIBRATION_RUNS runs, in seconds.
	 */
	private function time_runs( callable $run ): float {
		$best = INF;

		for ( $pass = 0; $pass < 3; $pass++ ) {
			$overhead = Clock::get_overhead();
			$start    = microtime( true );

			for ( $i = 0; $i < self::CALIBRATION_RUNS; $i++ ) {
				$run();
			}

			$time = microtime( true ) - $start - ( Clock::get_overhead() - $overhead );

			if ( $pass > 0 ) {
				$best = min( $best, $time );
			}
		}

		return $best;
	}

	/**
	 * Adds each callback that ran to the report.
	 *
	 * Entries are keyed by hook, priority and the callback's WP_Hook ID. A
	 * callback that was wrapped more than once is added up into one entry.
	 */
	public function record_callbacks(): void {
		$raw_start = microtime( true );

		try {
			$entries = array();

			foreach ( $this->wrapped as $item ) {
				$timer = $item['timer'];

				if ( 0 === $timer->get_calls() ) {
					continue;
				}

				$key = $item['hook'] . ' ' . $item['priority'] . ' ' . $item['id'];

				if ( ! isset( $entries[ $key ] ) ) {
					$location = get_callback_location( $timer->get_callback() );
					$source   = get_file_source( $location ? $location['file'] : null );

					$entries[ $key ] = array(
						'hook'        => $item['hook'],
						'priority'    => $item['priority'],
						'callback'    => get_callback_name( $timer->get_callback() ),
						'source_type' => $source['type'],
						'source_name' => $source['name'],
						'location'    => format_callback_location( $location ),
						'calls'       => 0,
						'total_time'  => 0.0,
						'self_time'   => 0.0,
					);
				}

				$entries[ $key ]['calls']      += $timer->get_calls();
				$entries[ $key ]['total_time'] += $timer->get_total_time();
				$entries[ $key ]['self_time']  += $timer->get_self_time();
			}

			foreach ( $entries as $key => $entry ) {
				$entry['total_time'] = seconds_to_ms( $entry['total_time'] );
				$entry['self_time']  = seconds_to_ms( $entry['self_time'] );

				add_report_entry( self::SECTION, $key, $entry );
			}
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}
}
