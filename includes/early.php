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
 * All profiler times use Clock::now(). It is on the same scale as
 * REQUEST_TIME_FLOAT, but it leaves out the time of the profiler's own work.
 */

// When PHP started handling the request.
define(
	'LIFECYCLE_PROFILER_REQUEST_START',
	isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true )
);

// When the mu-plugin loader ran.
define( 'LIFECYCLE_PROFILER_LOADER_START', microtime( true ) );

require_once __DIR__ . '/plugin-state.php';

// The loader can outlive the plugin. Do nothing if the plugin is not active.
if ( ! LifecycleProfiler\is_plugin_enabled() ) {
	return;
}

require_once __DIR__ . '/class-clock.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/report.php';
require_once __DIR__ . '/class-plugin-load-timer.php';
require_once __DIR__ . '/class-phase-timer.php';
require_once __DIR__ . '/class-timed-callback.php';
require_once __DIR__ . '/callbacks.php';
require_once __DIR__ . '/class-hook-timer.php';
require_once __DIR__ . '/class-template-timer.php';
require_once __DIR__ . '/class-template-part-timer.php';
require_once __DIR__ . '/class-block-timer.php';
require_once __DIR__ . '/server-timing.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/spans.php';
require_once __DIR__ . '/api.php';

if ( LifecycleProfiler\is_session_active() ) {
	define( 'LIFECYCLE_PROFILER_PROFILING', true );

	// Loading the profiler is profiler time. Count it before any timer starts.
	LifecycleProfiler\Clock::add_overhead_since( LIFECYCLE_PROFILER_LOADER_START );

	( new LifecycleProfiler\Plugin_Load_Timer() )->register();
	( new LifecycleProfiler\Phase_Timer() )->register();
	( new LifecycleProfiler\Hook_Timer() )->register();
	( new LifecycleProfiler\Template_Timer() )->register();
	( new LifecycleProfiler\Template_Part_Timer() )->register();
	( new LifecycleProfiler\Block_Timer() )->register();
	LifecycleProfiler\start_server_timing();
	add_action( 'shutdown', 'LifecycleProfiler\save_report', PHP_INT_MAX, 0 );
}
