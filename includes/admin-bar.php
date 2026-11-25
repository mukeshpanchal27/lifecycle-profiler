<?php
/**
 * Admin bar item that links to the report of the current page.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

use WP_Admin_Bar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle of the inline script that shows the request time in the admin bar.
 */
const ADMIN_BAR_SCRIPT = 'lifecycle-profiler-admin-bar';

/**
 * Checks whether the current request is being profiled.
 *
 * @return bool True if the timers run and the user is confirmed.
 */
function is_profiling_request(): bool {
	return defined( 'LIFECYCLE_PROFILER_PROFILING' ) && is_session_confirmed();
}

/**
 * Adds the "Profiler" item to the admin bar.
 *
 * During a profiled request, the item opens the report of the page it is on.
 * The report is saved at the end of the request, so it is ready by the time
 * the user clicks. Otherwise the item opens Tools > Profiler.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
 */
function add_admin_bar_item( WP_Admin_Bar $wp_admin_bar ): void {
	if ( ! current_user_can( SESSION_CAPABILITY ) ) {
		return;
	}

	$profiling = is_profiling_request();

	$wp_admin_bar->add_node(
		array(
			'id'    => 'lifecycle-profiler',
			'title' => '<span class="ab-label">' . esc_html__( 'Profiler', 'lifecycle-profiler' ) . '</span>',
			'href'  => $profiling ? get_admin_page_url( array( 'report' => get_current_report_id() ) ) : get_admin_page_url(),
			'meta'  => array(
				'title' => $profiling
					? __( 'Open the report of this page', 'lifecycle-profiler' )
					: __( 'Open the saved reports', 'lifecycle-profiler' ),
			),
		)
	);
}
add_action( 'admin_bar_menu', __NAMESPACE__ . '\add_admin_bar_item', 100 );

/**
 * Adds a small script that shows the request time in the admin bar item.
 *
 * The script adds up the phase times from the Server-Timing header, which the
 * browser reads from the response. Browsers only share the header with
 * scripts on HTTPS and localhost. Elsewhere the item keeps its plain label.
 */
function enqueue_admin_bar_script(): void {
	if ( ! is_admin_bar_showing() || ! current_user_can( SESSION_CAPABILITY ) || ! is_profiling_request() ) {
		return;
	}

	/* translators: %s: Request time in milliseconds, such as "53.7". */
	$format = __( 'Profiler: %s ms', 'lifecycle-profiler' );

	$script = <<<'JS'
window.addEventListener( 'load', function () {
	var nav = window.performance && performance.getEntriesByType ? performance.getEntriesByType( 'navigation' )[ 0 ] : null;
	var label = document.querySelector( '#wp-admin-bar-lifecycle-profiler .ab-label' );
	var total = 0;
	var found = false;

	if ( ! nav || ! nav.serverTiming || ! label ) {
		return;
	}

	nav.serverTiming.forEach( function ( metric ) {
		if ( 0 === metric.name.indexOf( 'lp-' ) && 'lp-overhead' !== metric.name ) {
			total += metric.duration;
			found = true;
		}
	} );

	if ( found ) {
		var number = new Intl.NumberFormat( document.documentElement.lang || undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 } );
		label.textContent = __FORMAT__.replace( '%s', number.format( total ) );
	}
} );
JS;

	wp_register_script( ADMIN_BAR_SCRIPT, false, array(), LIFECYCLE_PROFILER_VERSION, true );
	wp_add_inline_script( ADMIN_BAR_SCRIPT, str_replace( '__FORMAT__', (string) wp_json_encode( $format ), $script ) );
	wp_enqueue_script( ADMIN_BAR_SCRIPT );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_bar_script' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_admin_bar_script' );
