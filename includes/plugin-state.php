<?php
/**
 * Checks whether the plugin is active, before regular plugins load.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the plugin basename, such as "lifecycle-profiler/lifecycle-profiler.php".
 *
 * The main plugin file has not loaded yet when the loader runs, so the
 * basename comes from the folder of this file.
 *
 * @return string Plugin basename.
 */
function get_plugin_basename(): string {
	return basename( dirname( __DIR__ ) ) . '/lifecycle-profiler.php';
}

/**
 * Checks whether the plugin is active on this site or on the network.
 *
 * The loader can stay in mu-plugins after the plugin is deactivated, for
 * example when the active_plugins option is changed directly. Then the loader
 * must not start profiling. Both options are autoloaded, so this check does
 * not query the database.
 *
 * @return bool True if the plugin is active.
 */
function is_plugin_enabled(): bool {
	$basename = get_plugin_basename();

	if ( in_array( $basename, (array) get_option( 'active_plugins', array() ), true ) ) {
		return true;
	}

	return is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $basename ] );
}
