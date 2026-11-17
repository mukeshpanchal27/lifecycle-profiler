<?php
/**
 * Sends phase times in the Server-Timing response header.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts buffering output so the header can be sent at shutdown.
 *
 * Phase times are only complete at shutdown, when the page has usually been
 * sent. The buffer holds the page until then. WordPress flushes all output
 * buffers on shutdown at priority 1, after the header goes out.
 *
 * Call this from the mu-plugin loader, during a session only.
 */
function start_server_timing(): void {
	ob_start();

	// After Phase_Timer records phases at PHP_INT_MIN, before wp_ob_end_flush_all() at 1.
	add_action( 'shutdown', __NAMESPACE__ . '\send_server_timing_header', PHP_INT_MIN + 1, 0 );
}

/**
 * Sends the phase times in the Server-Timing header.
 */
function send_server_timing_header(): void {
	if ( headers_sent() || ! is_session_confirmed() ) {
		return;
	}

	$header = get_server_timing_header( get_report()['phases'] ?? array() );

	if ( '' !== $header ) {
		header( 'Server-Timing: ' . $header . ', ' . get_overhead_metric( Clock::get_overhead() ), false );
	}
}

/**
 * Builds the Server-Timing metric for the profiler's own time.
 *
 * The phase metrics leave this time out. This metric shows how much it was.
 *
 * @param float $seconds Profiler time in seconds.
 * @return string Metric such as 'lp-overhead;dur=6.120;desc="Profiler time left out"'.
 */
function get_overhead_metric( float $seconds ): string {
	return sprintf( 'lp-overhead;dur=%.3F;desc="Profiler time left out"', $seconds * 1000 );
}

/**
 * Builds the Server-Timing header value from phase entries.
 *
 * @param array<string, array<string, mixed>> $phases Phase entries from the report.
 * @return string Header value such as "lp-bootstrap;dur=4.492, lp-mu_plugins;dur=23.321".
 */
function get_server_timing_header( array $phases ): string {
	$metrics = array();

	foreach ( $phases as $name => $phase ) {
		// Metric names must be tokens: letters, digits, "-" and "_".
		$name = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $name );

		// %F ignores the locale, so the decimal point is always ".".
		$metrics[] = sprintf( 'lp-%s;dur=%.3F', $name, (float) $phase['duration'] );
	}

	return implode( ', ', $metrics );
}
