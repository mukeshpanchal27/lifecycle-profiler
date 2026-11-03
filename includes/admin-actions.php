<?php
/**
 * Form actions on saved reports.
 *
 * Each action runs through admin-post.php, checks the capability and a nonce
 * for the report, then goes back to the report screen.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action that changes the label of a report.
 */
const LABEL_ACTION = 'lifecycle_profiler_label';

/**
 * Action that deletes a report.
 */
const DELETE_ACTION = 'lifecycle_profiler_delete';

/**
 * Action that downloads a report as JSON.
 */
const EXPORT_ACTION = 'lifecycle_profiler_export';

/**
 * Format name in exported JSON files.
 */
const EXPORT_FORMAT = 'lifecycle-profiler-report';

/**
 * Version of the exported JSON format.
 */
const EXPORT_FORMAT_VERSION = 1;

/**
 * Returns the nonce action for one action on one report.
 *
 * @param string $action Action name.
 * @param string $id     Report ID.
 * @return string Nonce action.
 */
function get_report_nonce_action( string $action, string $id ): string {
	return $action . '_' . $id;
}

/**
 * Checks the capability and the nonce of a report action.
 *
 * Stops the request if a check fails.
 *
 * @param string $action Action name.
 * @return string ID of a report that exists.
 */
function check_report_action( string $action ): string {
	if ( ! current_user_can( SESSION_CAPABILITY ) ) {
		wp_die( esc_html__( 'You do not have permission to change profiler reports.', 'lifecycle-profiler' ), 403 );
	}

	// The nonce check below covers the report ID.
	$id = isset( $_REQUEST['report'] ) ? sanitize_key( wp_unslash( $_REQUEST['report'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	check_admin_referer( get_report_nonce_action( $action, $id ) );

	if ( ! isset( get_report_index()[ $id ] ) ) {
		wp_die( esc_html__( 'This report does not exist.', 'lifecycle-profiler' ), 404 );
	}

	return $id;
}

/**
 * Changes the label of a report.
 */
function handle_label(): void {
	$id    = check_report_action( LABEL_ACTION );
	$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check_report_action().

	update_report_label( $id, $label );

	wp_safe_redirect( get_admin_page_url( array( 'report' => $id ) ) );
	exit;
}
add_action( 'admin_post_' . LABEL_ACTION, __NAMESPACE__ . '\handle_label' );

/**
 * Deletes a report.
 */
function handle_delete(): void {
	delete_saved_report( check_report_action( DELETE_ACTION ) );

	wp_safe_redirect( get_admin_page_url() );
	exit;
}
add_action( 'admin_post_' . DELETE_ACTION, __NAMESPACE__ . '\handle_delete' );

/**
 * Returns the URL that downloads a report as JSON.
 *
 * @param string $id Report ID.
 * @return string Download URL.
 */
function get_export_url( string $id ): string {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => EXPORT_ACTION,
				'report' => $id,
			),
			admin_url( 'admin-post.php' )
		),
		get_report_nonce_action( EXPORT_ACTION, $id )
	);
}

/**
 * Sends a report as a JSON file download.
 */
function handle_export(): void {
	$id    = check_report_action( EXPORT_ACTION );
	$entry = get_report_index()[ $id ];

	$data = array(
		'format'         => EXPORT_FORMAT,
		'format_version' => EXPORT_FORMAT_VERSION,
		'plugin_version' => LIFECYCLE_PROFILER_VERSION,
		'id'             => $id,
		'label'          => $entry['label'] ?? '',
		'report'         => get_saved_report( $id ),
	);

	$filename = sprintf( 'lifecycle-profiler-%s-%s.json', gmdate( 'Y-m-d-His', (int) ( $entry['time'] ?? 0 ) ), $id );

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'admin_post_' . EXPORT_ACTION, __NAMESPACE__ . '\handle_export' );

/**
 * Stops the profiler's own form actions from saving a report.
 *
 * @param bool $save Whether to save the report.
 * @return bool False for a profiler action, otherwise unchanged.
 */
function skip_saving_own_actions( $save ) {
	global $pagenow;

	// Only the action name is read here. Each handler checks its own nonce.
	$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( 'admin-post.php' === $pagenow && 0 === strpos( $action, 'lifecycle_profiler_' ) ) {
		return false;
	}

	return $save;
}
add_filter( 'lifecycle_profiler_save_report', __NAMESPACE__ . '\skip_saving_own_actions' );
