<?php
/**
 * Report data for the current request.
 *
 * Profiling code adds entries during the request. Later code reads the whole
 * report to save or show it.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the report storage by reference.
 *
 * @return array<string, array<string, array<string, mixed>>> Report data, by section and key.
 */
function &report_storage(): array {
	static $report = array();

	return $report;
}

/**
 * Adds an entry to a section of the report.
 *
 * An entry with the same section and key replaces the old one.
 *
 * @param string               $section Section name, such as "plugins".
 * @param string               $key     Entry key within the section.
 * @param array<string, mixed> $entry   Entry data.
 */
function add_report_entry( string $section, string $key, array $entry ): void {
	$report = &report_storage();

	$report[ $section ][ $key ] = $entry;
}

/**
 * Returns the report for the current request.
 *
 * @return array<string, array<string, array<string, mixed>>> Report data, by section and key.
 */
function get_report(): array {
	return report_storage();
}

/**
 * Converts a Clock::now() value to milliseconds since the request started.
 *
 * @param float $time Time from Clock::now().
 * @return float Milliseconds since the request started.
 */
function ms_since_request_start( float $time ): float {
	return round( ( $time - LIFECYCLE_PROFILER_REQUEST_START ) * 1000, 3 );
}

/**
 * Converts a duration in seconds to milliseconds.
 *
 * @param float $seconds Duration in seconds.
 * @return float Duration in milliseconds.
 */
function seconds_to_ms( float $seconds ): float {
	return round( $seconds * 1000, 3 );
}
