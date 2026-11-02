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

/**
 * Returns where a callback is defined.
 *
 * @param mixed $callback Callback as stored in WP_Hook.
 * @return array{file: string, line: int}|null File and line, or null for built-in PHP functions and callbacks that cannot be found.
 */
function get_callback_location( $callback ): ?array {
	try {
		if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			$callback = explode( '::', $callback, 2 );
		}

		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
			$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
		} elseif ( is_string( $callback ) || $callback instanceof Closure ) {
			$reflection = new \ReflectionFunction( $callback );
		} elseif ( is_object( $callback ) ) {
			$reflection = new \ReflectionMethod( $callback, '__invoke' );
		} else {
			return null;
		}
	} catch ( \ReflectionException $e ) {
		return null;
	}

	$file = $reflection->getFileName();

	if ( false === $file ) {
		return null;
	}

	return array(
		'file' => wp_normalize_path( $file ),
		'line' => (int) $reflection->getStartLine(),
	);
}

/**
 * Returns the source of a file: a plugin, theme, core, and so on.
 *
 * More specific folders are checked first, because the plugin, mu-plugin and
 * theme folders are all inside wp-content.
 *
 * @param string|null $file Normalized file path, or null for built-in PHP functions.
 * @return array{type: string, name: string} Source type and name. The type is
 *         one of "mu-plugin", "plugin", "theme", "dropin", "core", "php" or "other".
 */
function get_file_source( ?string $file ): array {
	static $cache = array();

	if ( null === $file ) {
		return array(
			'type' => 'php',
			'name' => 'PHP',
		);
	}

	if ( isset( $cache[ $file ] ) ) {
		return $cache[ $file ];
	}

	$folders = array(
		'mu-plugin' => array( WPMU_PLUGIN_DIR ),
		'plugin'    => array( WP_PLUGIN_DIR ),
		'theme'     => array_values( (array) ( $GLOBALS['wp_theme_directories'] ?? array( get_theme_root() ) ) ),
	);

	$source = null;

	foreach ( $folders as $type => $paths ) {
		foreach ( $paths as $path ) {
			$path = trailingslashit( wp_normalize_path( $path ) );

			if ( 0 === strpos( $file, $path ) ) {
				// The first folder or file name inside the folder, such as "akismet" or "hello.php".
				$source = array(
					'type' => $type,
					'name' => strtok( substr( $file, strlen( $path ) ), '/' ),
				);
				break 2;
			}
		}
	}

	if ( null === $source ) {
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		$abspath     = trailingslashit( wp_normalize_path( ABSPATH ) );

		if ( dirname( $file ) . '/' === $content_dir ) {
			$source = array(
				'type' => 'dropin',
				'name' => basename( $file ),
			);
		} elseif ( 0 === strpos( $file, $abspath ) && 0 !== strpos( $file, $content_dir ) ) {
			$source = array(
				'type' => 'core',
				'name' => 'WordPress',
			);
		} else {
			$source = array(
				'type' => 'other',
				'name' => basename( $file ),
			);
		}
	}

	$cache[ $file ] = $source;

	return $source;
}

/**
 * Returns a file path relative to the WordPress root folder.
 *
 * @param string $file Normalized file path.
 * @return string Path relative to ABSPATH, or the full path if it is outside ABSPATH.
 */
function get_relative_path( string $file ): string {
	$abspath = trailingslashit( wp_normalize_path( ABSPATH ) );

	return 0 === strpos( $file, $abspath ) ? substr( $file, strlen( $abspath ) ) : $file;
}
