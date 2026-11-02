<?php
/**
 * Public timing API.
 *
 * Other plugins and themes can call these functions to time their own code.
 * Outside a profiling session, the functions do nothing.
 *
 * Example:
 *
 *     lifecycle_profiler_start( 'my-plugin: build menu' );
 *     build_menu();
 *     lifecycle_profiler_stop( 'my-plugin: build menu' );
 *
 * @package LifecycleProfiler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a named span.
 *
 * @param string $name Span name. Spans with the same name add up in the report.
 */
function lifecycle_profiler_start( string $name ): void {
	if ( LifecycleProfiler\is_profiling() ) {
		LifecycleProfiler\start_span( $name );
	}
}

/**
 * Stops the most recent open span with this name.
 *
 * @param string $name Span name.
 * @return float|null Duration of this span in milliseconds, or null outside a session or if no span with this name is open.
 */
function lifecycle_profiler_stop( string $name ): ?float {
	if ( ! LifecycleProfiler\is_profiling() ) {
		return null;
	}

	return LifecycleProfiler\stop_span( $name );
}
