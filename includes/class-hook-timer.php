<?php
/**
 * Times every hook callback during a session.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps hook callbacks in Timed_Callback, and adds their times to the report.
 *
 * WordPress fires the "all" hook right before it runs the callbacks of any
 * hook. At that point this class wraps the callbacks of that hook. Only hooks
 * that fire are wrapped, and callbacks added later are wrapped the next time
 * their hook fires.
 *
 * Each callback keeps its key in WP_Hook, so remove_action() and has_filter()
 * with the original callable keep working.
 */
final class Hook_Timer {

	/**
	 * Report section for callback times.
	 */
	const SECTION = 'callbacks';

	/**
	 * Every wrapper created, with where it sits.
	 *
	 * @var array<int, array{hook: string, priority: int, id: string, timer: Timed_Callback}>
	 */
	private $wrapped = array();

	/**
	 * Adds the hooks. Call this from the mu-plugin loader.
	 */
	public function register(): void {
		add_action( 'all', array( $this, 'wrap_callbacks' ), PHP_INT_MIN );

		// Before the report is saved at PHP_INT_MAX.
		add_action( 'shutdown', array( $this, 'record_callbacks' ), PHP_INT_MAX - 10, 0 );
	}

	/**
	 * Wraps the callbacks of the hook that is about to run.
	 *
	 * @param string $hook_name Name of the hook about to run.
	 */
	public function wrap_callbacks( $hook_name ): void {
		global $wp_filter;

		if ( 'all' === $hook_name || ! isset( $wp_filter[ $hook_name ] ) ) {
			return;
		}

		$hook = $wp_filter[ $hook_name ];

		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $id => $callback ) {
				if ( $callback['function'] instanceof Timed_Callback || is_own_callback( $callback['function'] ) ) {
					continue;
				}

				$timer = new Timed_Callback( $callback['function'] );

				$hook->callbacks[ $priority ][ $id ]['function'] = $timer;

				$this->wrapped[] = array(
					'hook'     => (string) $hook_name,
					'priority' => (int) $priority,
					'id'       => (string) $id,
					'timer'    => $timer,
				);
			}
		}
	}

	/**
	 * Adds each callback that ran to the report.
	 *
	 * Entries are keyed by hook, priority and the callback's WP_Hook ID. A
	 * callback that was wrapped more than once is added up into one entry.
	 */
	public function record_callbacks(): void {
		$entries = array();

		foreach ( $this->wrapped as $item ) {
			$timer = $item['timer'];

			if ( 0 === $timer->get_calls() ) {
				continue;
			}

			$key = $item['hook'] . ' ' . $item['priority'] . ' ' . $item['id'];

			if ( ! isset( $entries[ $key ] ) ) {
				$entries[ $key ] = array(
					'hook'       => $item['hook'],
					'priority'   => $item['priority'],
					'callback'   => get_callback_name( $timer->get_callback() ),
					'calls'      => 0,
					'total_time' => 0.0,
					'self_time'  => 0.0,
				);
			}

			$entries[ $key ]['calls']      += $timer->get_calls();
			$entries[ $key ]['total_time'] += $timer->get_total_time();
			$entries[ $key ]['self_time']  += $timer->get_self_time();
		}

		foreach ( $entries as $key => $entry ) {
			$entry['total_time'] = seconds_to_ms( $entry['total_time'] );
			$entry['self_time']  = seconds_to_ms( $entry['self_time'] );

			add_report_entry( self::SECTION, $key, $entry );
		}
	}
}
