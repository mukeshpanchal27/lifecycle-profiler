<?php
/**
 * Times block rendering for each block type.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Times each block render, and adds the times up by block type.
 *
 * WordPress fires pre_render_block before it renders a block, and render_block
 * after. Both fire for top-level and inner blocks. A block's total time
 * includes its inner blocks. Its self time leaves them out.
 *
 * A block type can nest inside itself, such as a group inside a group. Only
 * the outer block adds to the total time of its type, so the time of the inner
 * block is not counted twice.
 *
 * Blocks without a name are skipped. They are mostly the empty space between
 * blocks.
 */
final class Block_Timer {

	/**
	 * Report section for block times.
	 */
	const SECTION = 'blocks';

	/**
	 * Blocks that started but did not end yet, innermost last.
	 *
	 * @var array<int, array{name: string, start: float, inner_time: float}>
	 */
	private $open = array();

	/**
	 * Calls, total time and self time of each block type, by name.
	 *
	 * @var array<string, array{name: string, calls: int, total_time: float, self_time: float}>
	 */
	private $blocks = array();

	/**
	 * Number of open blocks of each type, by name.
	 *
	 * @var array<string, int>
	 */
	private $depth = array();

	/**
	 * Adds the hooks. Call this from the mu-plugin loader.
	 */
	public function register(): void {
		// Last, so the timer sees whether another filter returned content.
		add_filter( 'pre_render_block', array( $this, 'start' ), PHP_INT_MAX, 2 );
		// Last, so the time includes the other render_block filters.
		add_filter( 'render_block', array( $this, 'stop' ), PHP_INT_MAX, 2 );

		// Before the report is saved at PHP_INT_MAX.
		add_action( 'shutdown', array( $this, 'record_blocks' ), PHP_INT_MAX - 10, 0 );
	}

	/**
	 * Starts timing a block.
	 *
	 * If an earlier filter returned content, WordPress does not render the
	 * block and does not fire render_block, so the timer does not start.
	 *
	 * @param string|null          $pre          Content from an earlier filter, or null to render the block.
	 * @param array<string, mixed> $parsed_block Parsed block.
	 * @return string|null The same value.
	 */
	public function start( $pre, $parsed_block ) {
		$raw_start = microtime( true );

		try {
			$name = $this->get_block_name( $parsed_block );

			if ( null === $pre && null !== $name ) {
				$this->open[] = array(
					'name'       => $name,
					'start'      => Clock::now(),
					'inner_time' => 0.0,
				);

				$this->depth[ $name ] = ( $this->depth[ $name ] ?? 0 ) + 1;
			}

			return $pre;
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}

	/**
	 * Stops timing a block and adds its time to its block type.
	 *
	 * Does nothing if the innermost open block has another name. That happens
	 * when code calls WP_Block::render() directly, which skips pre_render_block.
	 *
	 * @param string               $content Rendered block content.
	 * @param array<string, mixed> $block   Parsed block.
	 * @return string The same content.
	 */
	public function stop( $content, $block ) {
		$raw_start = microtime( true );

		try {
			$name = $this->get_block_name( $block );
			$last = end( $this->open );

			if ( null === $name || false === $last || $last['name'] !== $name ) {
				return $content;
			}

			array_pop( $this->open );
			--$this->depth[ $name ];

			$total = Clock::now() - $last['start'];

			if ( array() !== $this->open ) {
				$this->open[ count( $this->open ) - 1 ]['inner_time'] += $total;
			}

			if ( ! isset( $this->blocks[ $name ] ) ) {
				$this->blocks[ $name ] = array(
					'name'       => $name,
					'calls'      => 0,
					'total_time' => 0.0,
					'self_time'  => 0.0,
				);
			}

			++$this->blocks[ $name ]['calls'];
			$this->blocks[ $name ]['self_time'] += $total - $last['inner_time'];

			if ( 0 === $this->depth[ $name ] ) {
				$this->blocks[ $name ]['total_time'] += $total;
			}

			return $content;
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}

	/**
	 * Adds each block type to the report.
	 */
	public function record_blocks(): void {
		$raw_start = microtime( true );

		try {
			foreach ( $this->blocks as $name => $block ) {
				$block['total_time'] = seconds_to_ms( $block['total_time'] );
				$block['self_time']  = seconds_to_ms( $block['self_time'] );

				add_report_entry( self::SECTION, $name, $block );
			}
		} finally {
			Clock::add_overhead_since( $raw_start );
		}
	}

	/**
	 * Returns the name of a parsed block.
	 *
	 * @param mixed $block Parsed block.
	 * @return string|null Block name, such as "core/paragraph", or null if it has none.
	 */
	private function get_block_name( $block ): ?string {
		if ( ! is_array( $block ) || ! isset( $block['blockName'] ) || ! is_string( $block['blockName'] ) ) {
			return null;
		}

		return $block['blockName'];
	}
}
