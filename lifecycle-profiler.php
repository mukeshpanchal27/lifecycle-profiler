<?php
/**
 * Plugin Name:       Lifecycle Profiler
 * Plugin URI:        https://github.com/mukeshpanchal27/lifecycle-profiler
 * Description:       Times the whole WordPress request, from bootstrap and plugin load to every hook callback.
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Version:           0.3.0
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

define( 'LIFECYCLE_PROFILER_VERSION', '0.3.0' );
define( 'LIFECYCLE_PROFILER_FILE', __FILE__ );
define( 'LIFECYCLE_PROFILER_DIR', plugin_dir_path( __FILE__ ) );

require_once LIFECYCLE_PROFILER_DIR . 'includes/loader.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/session.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/report.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/storage.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/summary.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/compare.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/spans.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/api.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/admin-bar.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/admin-page.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/admin-actions.php';
require_once LIFECYCLE_PROFILER_DIR . 'includes/admin-compare.php';

register_activation_hook( __FILE__, 'LifecycleProfiler\install_loader' );
register_deactivation_hook( __FILE__, 'LifecycleProfiler\remove_loader' );
