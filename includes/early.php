<?php
/**
 * Runs from the mu-plugin loader, before regular plugins load.
 *
 * @package LifecycleProfiler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIFECYCLE_PROFILER_EARLY_LOADED', true );
