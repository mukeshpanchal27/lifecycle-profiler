<?php
/**
 * Plugin Name:       Lifecycle Profiler
 * Plugin URI:        https://github.com/mukeshpanchal27/lifecycle-profiler
 * Description:       Times the whole WordPress request, from bootstrap and plugin load to every hook callback.
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Version:           0.0.1
 * Author:            Mukesh Panchal
 * Author URI:        https://github.com/mukeshpanchal27
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       lifecycle-profiler
 *
 * @package LifecycleProfiler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIFECYCLE_PROFILER_VERSION', '0.0.1' );
define( 'LIFECYCLE_PROFILER_FILE', __FILE__ );
define( 'LIFECYCLE_PROFILER_DIR', plugin_dir_path( __FILE__ ) );
