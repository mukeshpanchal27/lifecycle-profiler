<?php
/**
 * Helpers for describing hook callbacks.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

use Closure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a readable name for a callback.
 *
 * @param mixed $callback Callback as stored in WP_Hook.
 * @return string Name such as "my_function", "My_Class::method" or "Closure".
 */
function get_callback_name( $callback ): string {
	if ( is_string( $callback ) ) {
		return $callback;
	}

	if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && is_string( $callback[1] ) ) {
		if ( is_object( $callback[0] ) ) {
			return get_class( $callback[0] ) . '->' . $callback[1];
		}

		if ( is_string( $callback[0] ) ) {
			return $callback[0] . '::' . $callback[1];
		}
	}

	if ( $callback instanceof Closure ) {
		return 'Closure';
	}

	if ( is_object( $callback ) ) {
		return get_class( $callback ) . '->__invoke';
	}

	return 'Unknown';
}

/**
 * Checks whether a callback belongs to Lifecycle Profiler.
 *
 * The profiler does not time its own callbacks.
 *
 * @param mixed $callback Callback as stored in WP_Hook.
 * @return bool True if the callback is a Lifecycle Profiler function or method.
 */
function is_own_callback( $callback ): bool {
	if ( is_array( $callback ) && isset( $callback[0] ) ) {
		$callback = $callback[0];
	}

	if ( is_object( $callback ) ) {
		$callback = get_class( $callback );
	}

	return is_string( $callback ) && 0 === strpos( ltrim( $callback, '\\' ), __NAMESPACE__ . '\\' );
}
