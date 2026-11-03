<?php
/**
 * Saves and reads reports.
 *
 * Each report is saved in its own option. An index option lists every saved
 * report with its label, URL and date, so the report list does not need to
 * load the full reports.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option that lists the saved reports.
 */
const REPORT_INDEX_OPTION = 'lifecycle_profiler_reports';

/**
 * Prefix of the option that stores one report. The report ID follows it.
 */
const REPORT_OPTION_PREFIX = 'lifecycle_profiler_report_';

/**
 * How many reports to keep by default.
 */
const DEFAULT_MAX_REPORTS = 20;

/**
 * Option that stored the only report in version 0.2 and earlier.
 */
const LEGACY_REPORT_OPTION = 'lifecycle_profiler_latest_report';

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

	add_saved_report( $report, microtime( true ) );
}

/**
 * Checks whether the report of the current request should be saved.
 *
 * AJAX, cron and REST requests are not saved by default. Background requests
 * such as the heartbeat would otherwise fill the report list.
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
 * Saves a report and adds it to the index.
 *
 * The index stores the save time with microseconds, so two reports saved in
 * the same second still sort in the order they were saved.
 *
 * @param array<string, mixed> $report Report data with a "meta" section.
 * @param float                $time   When the report was saved, as a Unix time.
 * @return string ID of the new report.
 */
function add_saved_report( array $report, float $time ): string {
	$id   = bin2hex( random_bytes( 6 ) );
	$meta = isset( $report['meta'] ) && is_array( $report['meta'] ) ? $report['meta'] : array();

	add_option( REPORT_OPTION_PREFIX . $id, $report, '', false );

	$index        = get_report_index();
	$index[ $id ] = array(
		'id'         => $id,
		'label'      => '',
		'url'        => (string) ( $meta['url'] ?? '' ),
		'method'     => (string) ( $meta['method'] ?? '' ),
		'time'       => $time,
		'total_time' => (float) ( $meta['total_time'] ?? 0 ),
	);

	update_report_index( $index );
	delete_old_reports();

	return $id;
}

/**
 * Returns how many reports to keep.
 *
 * @return int Number of reports, at least 1.
 */
function get_max_reports(): int {
	/**
	 * Filters how many reports to keep. When a new report goes over the limit,
	 * the oldest reports are deleted.
	 *
	 * @param int $max Number of reports. Default 20.
	 */
	return max( 1, (int) apply_filters( 'lifecycle_profiler_max_reports', DEFAULT_MAX_REPORTS ) );
}

/**
 * Deletes the oldest reports when there are more than the limit.
 */
function delete_old_reports(): void {
	$ids = array_keys( get_report_index() );

	foreach ( array_slice( $ids, get_max_reports() ) as $id ) {
		delete_saved_report( (string) $id );
	}
}

/**
 * Deletes a saved report and removes it from the index.
 *
 * @param string $id Report ID.
 * @return bool True if the report was in the index.
 */
function delete_saved_report( string $id ): bool {
	$index = get_report_index();

	if ( ! isset( $index[ $id ] ) ) {
		return false;
	}

	unset( $index[ $id ] );
	update_report_index( $index );
	delete_option( REPORT_OPTION_PREFIX . $id );

	return true;
}

/**
 * Returns the saved reports, newest first.
 *
 * @return array<string, array{id: string, label: string, url: string, method: string, time: float, total_time: float}>
 *         Index entries, by report ID.
 */
function get_report_index(): array {
	$index = get_option( REPORT_INDEX_OPTION );

	if ( ! is_array( $index ) ) {
		return array();
	}

	uasort(
		$index,
		static function ( array $a, array $b ): int {
			return $b['time'] <=> $a['time'];
		}
	);

	return $index;
}

/**
 * Saves the report index.
 *
 * @param array<string, array<string, mixed>> $index Index entries, by report ID.
 */
function update_report_index( array $index ): void {
	update_option( REPORT_INDEX_OPTION, $index, false );
}

/**
 * Checks whether a string has the form of a report ID.
 *
 * @param string $id Value to check.
 * @return bool True if the value can be a report ID.
 */
function is_report_id( string $id ): bool {
	return 1 === preg_match( '/^[a-f0-9]{12}$/', $id );
}

/**
 * Returns a saved report.
 *
 * @param string $id Report ID.
 * @return array<string, mixed>|null The report, or null if it does not exist.
 */
function get_saved_report( string $id ): ?array {
	if ( ! is_report_id( $id ) ) {
		return null;
	}

	$report = get_option( REPORT_OPTION_PREFIX . $id );

	return is_array( $report ) ? $report : null;
}

/**
 * Returns the ID of the newest saved report.
 *
 * @return string|null Report ID, or null if no report is saved.
 */
function get_latest_report_id(): ?string {
	$ids = array_keys( get_report_index() );

	return $ids ? (string) $ids[0] : null;
}

/**
 * Changes the label of a saved report.
 *
 * @param string $id    Report ID.
 * @param string $label New label. An empty label shows the URL instead.
 * @return bool True if the report exists.
 */
function update_report_label( string $id, string $label ): bool {
	$index = get_report_index();

	if ( ! isset( $index[ $id ] ) ) {
		return false;
	}

	$index[ $id ]['label'] = $label;
	update_report_index( $index );

	return true;
}

/**
 * Returns the text that names a report in lists.
 *
 * @param array<string, mixed> $entry Index entry.
 * @return string The label, or the method and URL if the label is empty.
 */
function get_report_title( array $entry ): string {
	if ( '' !== ( $entry['label'] ?? '' ) ) {
		return (string) $entry['label'];
	}

	return trim( ( $entry['method'] ?? '' ) . ' ' . ( $entry['url'] ?? '' ) );
}

/**
 * Moves the report saved by version 0.2 or earlier into the report list.
 */
function migrate_legacy_report(): void {
	$report = get_option( LEGACY_REPORT_OPTION );

	if ( false === $report ) {
		return;
	}

	if ( is_array( $report ) ) {
		add_saved_report( $report, (float) ( $report['meta']['time'] ?? time() ) );
	}

	delete_option( LEGACY_REPORT_OPTION );
}
add_action( 'admin_init', __NAMESPACE__ . '\migrate_legacy_report' );
