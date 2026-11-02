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
