<?php
/**
 * Totals per source: plugin, theme, core, and so on.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds up load time and hook time for each source in a report.
 *
 * Load time comes from the "plugins" section. Hook time is the self time of
 * the source's callbacks, so nested callbacks are never counted twice.
 *
 * @param array<string, mixed> $report Report data.
 * @return array<int, array{type: string, name: string, load_time: float, hook_time: float, total_time: float, callbacks: int, calls: int}>
 *         One row per source, slowest total first.
 */
function get_source_totals( array $report ): array {
	$rows = array();

	$add = static function ( string $type, string $name ) use ( &$rows ): string {
		$key = $type . ':' . $name;

		if ( ! isset( $rows[ $key ] ) ) {
			$rows[ $key ] = array(
				'type'       => $type,
				'name'       => $name,
				'load_time'  => 0.0,
				'hook_time'  => 0.0,
				'total_time' => 0.0,
				'callbacks'  => 0,
				'calls'      => 0,
			);
		}

		return $key;
	};

	foreach ( (array) ( $report['plugins'] ?? array() ) as $basename => $plugin ) {
		// Network plugins live in the same folder as other plugins.
		$type = 'mu-plugin' === $plugin['type'] ? 'mu-plugin' : 'plugin';
		$key  = $add( $type, strtok( (string) $basename, '/' ) );

		$rows[ $key ]['load_time'] += (float) $plugin['duration'];
	}

	foreach ( (array) ( $report['callbacks'] ?? array() ) as $callback ) {
		$key = $add( (string) $callback['source_type'], (string) $callback['source_name'] );

		$rows[ $key ]['hook_time'] += (float) $callback['self_time'];
		++$rows[ $key ]['callbacks'];
		$rows[ $key ]['calls'] += (int) $callback['calls'];
	}

	foreach ( $rows as $key => $row ) {
		$rows[ $key ]['total_time'] = $row['load_time'] + $row['hook_time'];
	}

	usort(
		$rows,
		static function ( array $a, array $b ): int {
			return $b['total_time'] <=> $a['total_time'];
		}
	);

	return $rows;
}
