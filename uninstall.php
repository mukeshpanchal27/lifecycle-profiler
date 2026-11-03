<?php
/**
 * Removes all plugin data when the plugin is deleted.
 *
 * Deletes every option that starts with "lifecycle_profiler_": the saved
 * reports, the report index and the session secret. On multisite, it does
 * this for every site. It also removes the mu-plugin loader if it is still
 * there.
 *
 * @package LifecycleProfiler
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes the plugin options and transient of the current site.
 */
function lifecycle_profiler_delete_site_data(): void {
	global $wpdb;

	// No API lists options by prefix. This also finds reports that are missing from the report index.
	$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'lifecycle_profiler_' ) . '%'
		)
	);

	foreach ( $names as $name ) {
		delete_option( $name );
	}

	delete_transient( 'lifecycle_profiler_loader_retry' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $lifecycle_profiler_site_id ) {
		switch_to_blog( $lifecycle_profiler_site_id );
		lifecycle_profiler_delete_site_data();
		restore_current_blog();
	}
} else {
	lifecycle_profiler_delete_site_data();
}

// Deactivation removes the loader, unless the plugin was deactivated without its hook.
$lifecycle_profiler_loader = WPMU_PLUGIN_DIR . '/0-lifecycle-profiler-loader.php';

if ( file_exists( $lifecycle_profiler_loader ) ) {
	wp_delete_file( $lifecycle_profiler_loader );
}
