<?php
/**
 * Compares two reports.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the times of a report that can be compared, by section and row.
 *
 * Sources use their total time. Hooks and blocks use self time, so nested
 * callbacks and inner blocks are not counted twice. Template parts use total
 * time, because the report has no self time for them.
 *
 * @param array<string, mixed> $report Report data.
 * @return array<string, array<string, array{label: string, time: float}>> Times in milliseconds.
 */
function get_comparable_times( array $report ): array {
	$times = array(
		'phases'         => array(),
		'sources'        => array(),
		'hooks'          => array(),
		'template'       => array(),
		'template_parts' => array(),
		'blocks'         => array(),
	);

	foreach ( (array) ( $report['phases'] ?? array() ) as $name => $phase ) {
		$times['phases'][ $name ] = array(
			'label' => $name . ' → ' . ( $phase['end'] ?? '' ),
			'time'  => (float) ( $phase['duration'] ?? 0 ),
		);
	}

	foreach ( get_source_totals( $report ) as $row ) {
		$times['sources'][ $row['type'] . ':' . $row['name'] ] = array(
			'label' => get_source_label( $row['type'], $row['name'] ),
			'time'  => (float) $row['total_time'],
		);
	}

	foreach ( (array) ( $report['callbacks'] ?? array() ) as $callback ) {
		$hook = (string) ( $callback['hook'] ?? '' );

		if ( ! isset( $times['hooks'][ $hook ] ) ) {
			$times['hooks'][ $hook ] = array(
				'label' => $hook,
				'time'  => 0.0,
			);
		}

		$times['hooks'][ $hook ]['time'] += (float) ( $callback['self_time'] ?? 0 );
	}

	if ( isset( $report['template']['main'] ) && is_array( $report['template']['main'] ) ) {
		$template = $report['template']['main'];

		$times['template']['main'] = array(
			'label' => (string) ( ! empty( $template['block_template'] ) ? $template['block_template'] : ( $template['file'] ?? '' ) ),
			'time'  => (float) ( $template['duration'] ?? 0 ),
		);
	}

	foreach ( (array) ( $report['template_parts'] ?? array() ) as $name => $part ) {
		$times['template_parts'][ $name ] = array(
			'label' => (string) $name,
			'time'  => (float) ( $part['total_time'] ?? 0 ),
		);
	}

	foreach ( (array) ( $report['blocks'] ?? array() ) as $name => $block ) {
		$times['blocks'][ $name ] = array(
			'label' => (string) $name,
			'time'  => (float) ( $block['self_time'] ?? 0 ),
		);
	}

	return $times;
}

/**
 * Puts the times of two reports next to each other.
 *
 * A row that is in only one report has null for the other report.
 *
 * @param array<string, mixed> $base   The report to compare from.
 * @param array<string, mixed> $target The report to compare to.
 * @return array<string, array<string, array{label: string, base: float|null, target: float|null}>>
 *         Rows by section and row key.
 */
function compare_reports( array $base, array $target ): array {
	$base_times   = get_comparable_times( $base );
	$target_times = get_comparable_times( $target );
	$sections     = array();

	foreach ( $base_times as $section => $rows ) {
		$keys = array_unique( array_merge( array_keys( $rows ), array_keys( $target_times[ $section ] ) ) );

		$sections[ $section ] = array();

		foreach ( $keys as $key ) {
			$a = $rows[ $key ] ?? null;
			$b = $target_times[ $section ][ $key ] ?? null;

			$labels = array_unique( array_filter( array( $a['label'] ?? '', $b['label'] ?? '' ) ) );

			$sections[ $section ][ $key ] = array(
				'label'  => implode( ' → ', $labels ),
				'base'   => $a ? $a['time'] : null,
				'target' => $b ? $b['time'] : null,
			);
		}
	}

	return $sections;
}
