<?php
/**
 * Runs from the mu-plugin loader, before regular plugins load.
 *
 * @package LifecycleProfiler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * All profiler times use microtime( true ), the same clock as
 * REQUEST_TIME_FLOAT, so durations between them are correct.
 */

// When PHP started handling the request.
define(
	'LIFECYCLE_PROFILER_REQUEST_START',
	isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true )
);

// When the mu-plugin loader ran.
define( 'LIFECYCLE_PROFILER_LOADER_START', microtime( true ) );

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/report.php';
require_once __DIR__ . '/class-plugin-load-timer.php';
require_once __DIR__ . '/class-phase-timer.php';
require_once __DIR__ . '/class-timed-callback.php';
require_once __DIR__ . '/callbacks.php';
require_once __DIR__ . '/class-hook-timer.php';
require_once __DIR__ . '/server-timing.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/spans.php';
require_once __DIR__ . '/api.php';

if ( LifecycleProfiler\is_session_active() ) {
	define( 'LIFECYCLE_PROFILER_PROFILING', true );

	( new LifecycleProfiler\Plugin_Load_Timer() )->register();
	( new LifecycleProfiler\Phase_Timer() )->register();
	( new LifecycleProfiler\Hook_Timer() )->register();
	LifecycleProfiler\start_server_timing();
	add_action( 'shutdown', 'LifecycleProfiler\save_report', PHP_INT_MAX, 0 );
}
