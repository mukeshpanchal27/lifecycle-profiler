<?php
/**
 * Admin bar toggle for profiling sessions.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

use WP_Admin_Bar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action name for the toggle request and its nonce.
 */
const TOGGLE_ACTION = 'lifecycle_profiler_toggle';

/**
 * Adds the "Profiler: ON" or "Profiler: OFF" item to the admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
 */
function add_admin_bar_toggle( WP_Admin_Bar $wp_admin_bar ): void {
	if ( ! current_user_can( SESSION_CAPABILITY ) ) {
		return;
	}

	$title = is_session_active()
		/* translators: Admin bar label when a profiling session is running. */
		? __( 'Profiler: ON', 'lifecycle-profiler' )
		/* translators: Admin bar label when no profiling session is running. */
		: __( 'Profiler: OFF', 'lifecycle-profiler' );

	$wp_admin_bar->add_node(
		array(
			'id'    => 'lifecycle-profiler',
			'title' => esc_html( $title ),
			'href'  => wp_nonce_url( add_query_arg( 'action', TOGGLE_ACTION, admin_url( 'admin-post.php' ) ), TOGGLE_ACTION ),
		)
	);
}
add_action( 'admin_bar_menu', __NAMESPACE__ . '\add_admin_bar_toggle', 100 );

/**
 * Starts or stops the session, then returns to the previous page.
 */
function handle_toggle(): void {
	if ( ! current_user_can( SESSION_CAPABILITY ) ) {
		wp_die( esc_html__( 'You do not have permission to start a profiling session.', 'lifecycle-profiler' ), 403 );
	}

	check_admin_referer( TOGGLE_ACTION );

	if ( is_session_active() ) {
		end_session();
	} else {
		start_session( get_current_user_id() );
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_' . TOGGLE_ACTION, __NAMESPACE__ . '\handle_toggle' );
