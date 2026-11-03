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
		header( 'Server-Timing: ' . $header, false );
	}
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
