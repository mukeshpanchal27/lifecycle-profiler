<?php
/**
 * Times the main phases of a request.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records when each main action starts, and turns those marks into phases.
 *
 * A phase runs from one mark to the next mark that fired. Some requests skip
 * some actions. Admin pages, for example, never fire template_redirect. Phases
 * are built only from the marks that fired.
 */
final class Phase_Timer {

	/**
	 * Actions that start a phase, in the order WordPress fires them.
	 */
	const ACTIONS = array(
		'muplugins_loaded',
		'plugins_loaded',
		'setup_theme',
		'after_setup_theme',
		'init',
		'wp_loaded',
		'template_redirect',
		'shutdown',
	);

	/**
	 * Start time of each phase, as name => microtime( true ), in firing order.
	 *
	 * @var array<string, float>
	 */
	private $marks = array();

	/**
	 * Adds the hooks. Call this from the mu-plugin loader.
	 */
	public function register(): void {
		$this->marks['bootstrap']  = LIFECYCLE_PROFILER_REQUEST_START;
		$this->marks['mu_plugins'] = LIFECYCLE_PROFILER_LOADER_START;

		foreach ( self::ACTIONS as $action ) {
			add_action( $action, array( $this, 'mark' ), PHP_INT_MIN, 0 );
		}

		add_action( 'shutdown', array( $this, 'record_phases' ), PHP_INT_MIN, 0 );
	}

	/**
	 * Records the start time of the current action.
	 *
	 * Only the first time an action fires counts.
	 */
	public function mark(): void {
		$action = current_action();

		if ( ! isset( $this->marks[ $action ] ) ) {
			$this->marks[ $action ] = microtime( true );
		}
	}

	/**
	 * Adds each phase to the report.
	 *
	 * The last mark, shutdown, only ends the phase before it.
	 */
	public function record_phases(): void {
		$names = array_keys( $this->marks );
		$count = count( $names );

		for ( $i = 0; $i < $count - 1; $i++ ) {
			$start = $this->marks[ $names[ $i ] ];
			$end   = $this->marks[ $names[ $i + 1 ] ];

			add_report_entry(
				'phases',
				$names[ $i ],
				array(
					'end'      => $names[ $i + 1 ],
					'start'    => ms_since_request_start( $start ),
					'duration' => seconds_to_ms( $end - $start ),
				)
			);
		}
	}
}
