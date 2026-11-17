<?php
/**
 * Times each template part.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Times template part files in classic themes and template part blocks in
 * block themes.
 *
 * See docs/decisions/0002-template-part-timing.md for the approach and its
 * limits. The time of a part includes the parts nested inside it.
 */
final class Template_Part_Timer {

	/**
	 * Report section for template parts.
	 */
	const SECTION = 'template_parts';

	/**
	 * Name of the template part block.
	 */
	const BLOCK_NAME = 'core/template-part';

	/**
	 * Parts that started but did not end yet, innermost last.
	 *
	 * @var array<int, array{name: string, start: float}>
	 */
	private $open = array();

	/**
	 * Calls and total time of each part, by name.
	 *
	 * @var array<string, array{name: string, type: string, calls: int, total_time: float}>
	 */
	private $parts = array();

	/**
	 * Adds the hooks. Call this from the mu-plugin loader.
	 */
	public function register(): void {
		add_action( 'wp_before_load_template', array( $this, 'start_file' ), PHP_INT_MAX, 1 );
		add_action( 'wp_after_load_template', array( $this, 'stop_file' ), PHP_INT_MIN, 1 );

		// Last, so the timer sees whether another filter returned content.
		add_filter( 'pre_render_block', array( $this, 'start_block' ), PHP_INT_MAX, 2 );
		// Last, so the time includes the other render_block filters.
		add_filter( 'render_block', array( $this, 'stop_block' ), PHP_INT_MAX, 2 );

		// Before the report is saved at PHP_INT_MAX.
		add_action( 'shutdown', array( $this, 'record_parts' ), PHP_INT_MAX - 10, 0 );
	}

	/**
	 * Starts timing a template part file.
	 *
	 * @param string $file Full path of the template file.
	 */
	public function start_file( $file ): void {
		$raw_start = microtime( true );

		try {
			$this->start( get_relative_path( wp_normalize_path( (string) $file ) ) );
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}

	/**
	 * Stops timing a template part file.
	 *
	 * @param string $file Full path of the template file.
	 */
	public function stop_file( $file ): void {
		$raw_start = microtime( true );

		try {
			$this->stop( get_relative_path( wp_normalize_path( (string) $file ) ), 'file' );
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}

	/**
	 * Starts timing a template part block.
	 *
	 * @param string|null          $pre          Content from an earlier filter, or null to render the block.
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @return string|null The same value.
	 */
	public function start_block( $pre, $parsed_block ) {
		$raw_start = microtime( true );

		try {
			if ( null === $pre && is_array( $parsed_block ) && self::BLOCK_NAME === ( $parsed_block['blockName'] ?? null ) ) {
				$this->start( $this->get_block_part_name( $parsed_block ) );
			}

			return $pre;
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}

	/**
	 * Stops timing a template part block.
	 *
	 * @param string               $content Rendered block content.
	 * @param array<string, mixed> $block   Parsed block.
	 * @return string The same content.
	 */
	public function stop_block( $content, $block ) {
		$raw_start = microtime( true );

		try {
			if ( is_array( $block ) && self::BLOCK_NAME === ( $block['blockName'] ?? null ) ) {
				$this->stop( $this->get_block_part_name( $block ), 'block' );
			}

			return $content;
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}

	/**
	 * Adds each template part to the report.
	 */
	public function record_parts(): void {
		$raw_start = microtime( true );

		try {
			foreach ( $this->parts as $name => $part ) {
				$part['total_time'] = seconds_to_ms( $part['total_time'] );

				add_report_entry( self::SECTION, $name, $part );
			}
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}

	/**
	 * Returns the name of a template part block, such as "twentytwentyfive//header".
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return string Part name.
	 */
	private function get_block_part_name( array $block ): string {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( ! isset( $attrs['slug'] ) || ! is_string( $attrs['slug'] ) ) {
			return self::BLOCK_NAME;
		}

		$theme = isset( $attrs['theme'] ) && is_string( $attrs['theme'] ) ? $attrs['theme'] : get_stylesheet();

		return $theme . '//' . $attrs['slug'];
	}

	/**
	 * Starts timing a part.
	 *
	 * @param string $name Part name.
	 */
	private function start( string $name ): void {
		$this->open[] = array(
			'name'  => $name,
			'start' => Clock::now(),
		);
	}

	/**
	 * Stops timing the innermost part and adds its time.
	 *
	 * Does nothing if the innermost open part has another name. That happens
	 * when a part ends without a matching start.
	 *
	 * @param string $name Part name.
	 * @param string $type Part type, "file" or "block".
	 */
	private function stop( string $name, string $type ): void {
		$last = end( $this->open );

		if ( false === $last || $last['name'] !== $name ) {
			return;
		}

		array_pop( $this->open );

		if ( ! isset( $this->parts[ $name ] ) ) {
			$this->parts[ $name ] = array(
				'name'       => $name,
				'type'       => $type,
				'calls'      => 0,
				'total_time' => 0.0,
			);
		}

		++$this->parts[ $name ]['calls'];
		$this->parts[ $name ]['total_time'] += Clock::now() - $last['start'];
	}
}
