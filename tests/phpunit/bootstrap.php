<?php
/**
 * Bootstraps the PHPUnit tests with the WordPress core test suite.
 *
 * The wp-env tests containers set WP_TESTS_DIR.
 *
 * @package LifecycleProfiler
 */

$lifecycle_profiler_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $lifecycle_profiler_tests_dir ) {
	$lifecycle_profiler_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $lifecycle_profiler_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find the WordPress test suite in ' . $lifecycle_profiler_tests_dir . '. Run the tests with "npm run test:php".' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// The core test suite reads this constant to find the polyfills.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

require_once $lifecycle_profiler_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/lifecycle-profiler.php';
	}
);

require $lifecycle_profiler_tests_dir . '/includes/bootstrap.php';
