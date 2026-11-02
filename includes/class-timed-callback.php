<?php
/**
 * A callable wrapper that records how long the callable takes.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps a callable, runs it with the same arguments, and records its time.
 *
 * Callbacks often run other timed callbacks, for example when a hook callback
 * fires another hook. Total time includes those nested callbacks. Self time
 * leaves them out, so adding up self time never counts the same time twice.
 *
 * Limits:
 * - Arguments are passed by value. A callable that takes an argument by
 *   reference still runs, without a warning, but it gets a copy. Changes it
 *   makes to an array or scalar argument are lost. Objects are not affected.
 * - If the callable ends the request with exit, that call is not recorded.
 */
final class Timed_Callback {

	/**
	 * Nested time of the timed callbacks that are running, innermost last.
	 *
	 * @var float[]
	 */
	private static $stack = array();

	/**
	 * The wrapped callable.
	 *
	 * Not typed as callable, because a hook callback may only become
	 * callable later, for example when a function is defined after
	 * add_action() runs.
	 *
	 * @var mixed
	 */
	private $callback;

	/**
	 * Number of completed calls.
	 *
	 * @var int
	 */
	private $calls = 0;

	/**
	 * Total time of all calls, in seconds.
	 *
	 * @var float
	 */
	private $total_time = 0.0;

	/**
	 * Self time of all calls, in seconds.
	 *
	 * @var float
	 */
	private $self_time = 0.0;

	/**
	 * Constructor.
	 *
	 * @param mixed $callback The callable to wrap.
	 */
	public function __construct( $callback ) {
		$this->callback = $callback;
	}

	/**
	 * Runs the wrapped callable and records its time.
	 *
	 * @param mixed ...$args Arguments for the callable.
	 * @return mixed What the callable returns.
	 */
	public function __invoke( ...$args ) {
		/*
		 * Pass references to the local copies. A callable that takes an
		 * argument by reference then runs without a PHP warning.
		 */
		$refs = array();
		foreach ( $args as $key => &$arg ) {
			$refs[ $key ] = &$arg;
		}
		unset( $arg );

		self::$stack[] = 0.0;
		$start         = microtime( true );

		try {
			return call_user_func_array( $this->callback, $refs );
		} finally {
			$elapsed = microtime( true ) - $start;
			$nested  = array_pop( self::$stack );

			++$this->calls;
			$this->total_time += $elapsed;
			$this->self_time  += $elapsed - $nested;

			$parent = count( self::$stack ) - 1;
			if ( $parent >= 0 ) {
				self::$stack[ $parent ] += $elapsed;
			}
		}
	}

	/**
	 * Returns the wrapped callable.
	 *
	 * @return mixed The wrapped callable.
	 */
	public function get_callback() {
		return $this->callback;
	}

	/**
	 * Returns the number of completed calls.
	 *
	 * @return int Number of calls.
	 */
	public function get_calls(): int {
		return $this->calls;
	}

	/**
	 * Returns the total time of all calls.
	 *
	 * @return float Total time in seconds.
	 */
	public function get_total_time(): float {
		return $this->total_time;
	}

	/**
	 * Returns the self time of all calls.
	 *
	 * @return float Self time in seconds.
	 */
	public function get_self_time(): float {
		return $this->self_time;
	}
}
