<?php
/**
 * Custom spans recorded through the timing API.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the start times of open spans by reference.
 *
 * @return array<string, float[]> Start times, by span name. Nested spans with the same name stack up.
 */
function &span_starts(): array {
	static $starts = array();

	return $starts;
}

/**
 * Checks whether the current request is being profiled.
 *
 * The mu-plugin loader defines the constant during a session. This check is
 * cheap, so the timing API can call it every time.
 *
 * @return bool True during a session.
 */
function is_profiling(): bool {
	return defined( 'LIFECYCLE_PROFILER_PROFILING' ) && LIFECYCLE_PROFILER_PROFILING;
}

/**
 * Starts a span.
 *
 * @param string $name Span name.
 */
function start_span( string $name ): void {
	$starts = &span_starts();

	$starts[ $name ][] = Clock::now();
}

/**
 * Stops the most recent open span with this name and adds it to the report.
 *
 * Spans with the same name add up into one report entry.
 *
 * @param string $name Span name.
 * @return float|null Duration of this span in milliseconds, or null if no span with this name is open.
 */
function stop_span( string $name ): ?float {
	$starts = &span_starts();

	if ( empty( $starts[ $name ] ) ) {
		return null;
	}

	$start    = array_pop( $starts[ $name ] );
	$duration = seconds_to_ms( Clock::now() - $start );

	$entry = get_report()['spans'][ $name ] ?? array(
		'start'      => ms_since_request_start( $start ),
		'calls'      => 0,
		'total_time' => 0.0,
	);

	++$entry['calls'];
	$entry['total_time'] = round( $entry['total_time'] + $duration, 3 );

	add_report_entry( 'spans', $name, $entry );

	return $duration;
}
