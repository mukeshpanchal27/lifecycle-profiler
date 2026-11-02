<?php
/**
 * Saves and reads reports.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option that stores the latest report.
 */
const LATEST_REPORT_OPTION = 'lifecycle_profiler_latest_report';

/**
 * Saves the report of the current request.
 *
 * Runs at the end of shutdown, after every other part of the report is
 * recorded.
 */
function save_report(): void {
	if ( ! is_session_active() || ! should_save_report() ) {
		return;
	}

	$report = get_report();

	if ( array() === $report ) {
		return;
	}

	$report['meta'] = array(
		'url'        => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
		'method'     => isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '',
		'time'       => time(),
		'user_id'    => get_current_user_id(),
		'total_time' => ms_since_request_start( microtime( true ) ),
	);

	update_option( LATEST_REPORT_OPTION, $report, false );
}

/**
 * Checks whether the report of the current request should be saved.
 *
 * AJAX, cron and REST requests are not saved by default. Background requests
 * such as the heartbeat would otherwise replace the report of the page right
 * away.
 *
 * @return bool True if the report should be saved.
 */
function should_save_report(): bool {
	$save = ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );

	/**
	 * Filters whether to save the report of the current request.
	 *
	 * @param bool $save Whether to save the report.
	 */
	return (bool) apply_filters( 'lifecycle_profiler_save_report', $save );
}

/**
 * Returns the latest saved report.
 *
 * @return array<string, mixed>|null The report, or null if none is saved.
 */
function get_latest_report(): ?array {
	$report = get_option( LATEST_REPORT_OPTION );

	return is_array( $report ) ? $report : null;
}
