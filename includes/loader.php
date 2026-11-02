<?php
/**
 * Installs and removes the mu-plugin loader.
 *
 * The loader runs before regular plugins, so the profiler can time how long
 * each plugin takes to load.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File name of the loader in the mu-plugins directory.
 *
 * WordPress loads mu-plugins in file name order, so the "0-" prefix makes the
 * loader run before most other mu-plugins.
 */
const LOADER_FILENAME = '0-lifecycle-profiler-loader.php';

/**
 * Returns the full path of the loader file.
 *
 * @return string Loader file path.
 */
function get_loader_path(): string {
	return WPMU_PLUGIN_DIR . '/' . LOADER_FILENAME;
}

/**
 * Returns the PHP code of the loader file.
 *
 * The loader finds the plugin relative to WP_PLUGIN_DIR, so it keeps working
 * if the site moves to another path.
 *
 * @return string Loader file contents.
 */
function get_loader_contents(): string {
	$early_file = dirname( plugin_basename( LIFECYCLE_PROFILER_FILE ) ) . '/includes/early.php';

	$template = <<<'PHP'
<?php
/**
 * Plugin Name: Lifecycle Profiler Loader
 * Description: Loads Lifecycle Profiler before other plugins. The Lifecycle Profiler plugin adds and removes this file.
 *
 * @package LifecycleProfiler
 */

$lifecycle_profiler_early_file = WP_PLUGIN_DIR . '/' . __EARLY_FILE__;
if ( is_readable( $lifecycle_profiler_early_file ) ) {
	require_once $lifecycle_profiler_early_file;
}
unset( $lifecycle_profiler_early_file );

PHP;

	// var_export() writes the path as a safe PHP string literal.
	return str_replace( '__EARLY_FILE__', var_export( $early_file, true ), $template ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
}

/**
 * Writes the loader file to the mu-plugins directory.
 *
 * Runs on plugin activation. It only writes when WordPress can use direct
 * file access, so activation never asks for FTP credentials.
 *
 * @return bool True if the loader file was written.
 */
function install_loader(): bool {
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
		return false;
	}

	if ( 'direct' !== get_filesystem_method( array(), WPMU_PLUGIN_DIR ) || ! WP_Filesystem() ) {
		return false;
	}

	global $wp_filesystem;

	return (bool) $wp_filesystem->put_contents( get_loader_path(), get_loader_contents(), FS_CHMOD_FILE );
}

/**
 * Removes the loader file from the mu-plugins directory.
 *
 * Runs on plugin deactivation.
 */
function remove_loader(): void {
	if ( file_exists( get_loader_path() ) ) {
		wp_delete_file( get_loader_path() );
	}
}

/**
 * Checks whether the loader file exists.
 *
 * @return bool True if the loader file exists.
 */
function is_loader_installed(): bool {
	return file_exists( get_loader_path() );
}

/**
 * Transient that pauses new write attempts after a failed one.
 */
const LOADER_RETRY_TRANSIENT = 'lifecycle_profiler_loader_retry';

/**
 * Writes the loader file when it is missing.
 *
 * The activation hook does not run when a site updates the plugin without
 * deactivating it, so the loader may never have been written. After a failed
 * write, this function waits an hour before it tries again.
 */
function maybe_install_loader(): void {
	if ( is_loader_installed() || ! current_user_can( 'activate_plugins' ) || get_transient( LOADER_RETRY_TRANSIENT ) ) {
		return;
	}

	if ( ! install_loader() ) {
		set_transient( LOADER_RETRY_TRANSIENT, 1, HOUR_IN_SECONDS );
	}
}
add_action( 'admin_init', __NAMESPACE__ . '\maybe_install_loader' );

/**
 * Shows an error notice when the loader file is missing.
 *
 * Without the loader, the profiler cannot time bootstrap or plugin load.
 */
function render_loader_notice(): void {
	if ( is_loader_installed() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	wp_admin_notice(
		sprintf(
			/* translators: %s: Path to the mu-plugins directory. */
			esc_html__( 'Lifecycle Profiler could not write its loader file to %s. Make the directory writable, then deactivate and activate the plugin again.', 'lifecycle-profiler' ),
			'<code>' . esc_html( WPMU_PLUGIN_DIR ) . '</code>'
		),
		array( 'type' => 'error' )
	);
}
add_action( 'admin_notices', __NAMESPACE__ . '\render_loader_notice' );
add_action( 'network_admin_notices', __NAMESPACE__ . '\render_loader_notice' );
