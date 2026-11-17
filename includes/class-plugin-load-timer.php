<?php
/**
 * Times how long each plugin takes to load.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records the load time of each mu-plugin, network plugin and plugin.
 *
 * WordPress fires an action right after it includes each plugin file. The
 * load time of a plugin is the time since the previous plugin of the same
 * type loaded. For the first plugin of a type, the start is the last read of
 * the option that lists those plugins. WordPress reads that option just
 * before it includes the first plugin, so core work before that point is not
 * counted as plugin load time.
 */
final class Plugin_Load_Timer {

	/**
	 * When the previous plugin finished loading, from Clock::now().
	 *
	 * @var float
	 */
	private $last;

	/**
	 * Plugin types that have started loading, as type => true.
	 *
	 * @var array<string, bool>
	 */
	private $started = array();

	/**
	 * Whether the next mu_plugin_loaded action is for the Lifecycle Profiler loader.
	 *
	 * @var bool
	 */
	private $skip_next_mu_plugin = true;

	/**
	 * Adds the hooks. Call this from the mu-plugin loader.
	 */
	public function register(): void {
		$this->last = Clock::now();

		add_action( 'mu_plugin_loaded', array( $this, 'record_mu_plugin' ) );
		add_filter( 'site_option_active_sitewide_plugins', array( $this, 'mark_network_plugins_start' ) );
		add_action( 'network_plugin_loaded', array( $this, 'record_network_plugin' ) );
		add_filter( 'option_active_plugins', array( $this, 'mark_plugins_start' ) );
		add_action( 'plugin_loaded', array( $this, 'record_plugin' ) );
		add_action( 'plugins_loaded', array( $this, 'unregister' ), PHP_INT_MIN );
	}

	/**
	 * Removes the hooks once all plugins have loaded.
	 */
	public function unregister(): void {
		remove_action( 'mu_plugin_loaded', array( $this, 'record_mu_plugin' ) );
		remove_filter( 'site_option_active_sitewide_plugins', array( $this, 'mark_network_plugins_start' ) );
		remove_action( 'network_plugin_loaded', array( $this, 'record_network_plugin' ) );
		remove_filter( 'option_active_plugins', array( $this, 'mark_plugins_start' ) );
		remove_action( 'plugin_loaded', array( $this, 'record_plugin' ) );
		remove_action( 'plugins_loaded', array( $this, 'unregister' ), PHP_INT_MIN );
	}

	/**
	 * Records the load time of a mu-plugin.
	 *
	 * The first call is for the Lifecycle Profiler loader itself. The loader
	 * is still running when register() is called, so its time is not
	 * recorded.
	 *
	 * @param string $file Full path of the mu-plugin file.
	 */
	public function record_mu_plugin( string $file ): void {
		if ( $this->skip_next_mu_plugin ) {
			$this->skip_next_mu_plugin = false;
			$this->last                = Clock::now();
			return;
		}

		$this->record( 'mu-plugin', $file );
	}

	/**
	 * Marks the start of network plugin loading.
	 *
	 * @param mixed $value Option value, passed through unchanged.
	 * @return mixed Option value.
	 */
	public function mark_network_plugins_start( $value ) {
		$this->mark_start( 'network-plugin' );

		return $value;
	}

	/**
	 * Records the load time of a network plugin.
	 *
	 * @param string $file Full path of the plugin file.
	 */
	public function record_network_plugin( string $file ): void {
		$this->record( 'network-plugin', $file );
	}

	/**
	 * Marks the start of plugin loading.
	 *
	 * @param mixed $value Option value, passed through unchanged.
	 * @return mixed Option value.
	 */
	public function mark_plugins_start( $value ) {
		$this->mark_start( 'plugin' );

		return $value;
	}

	/**
	 * Records the load time of a plugin.
	 *
	 * @param string $file Full path of the plugin file.
	 */
	public function record_plugin( string $file ): void {
		$this->record( 'plugin', $file );
	}

	/**
	 * Moves the start time to now, until the first plugin of a type loads.
	 *
	 * A plugin may read the same option while it loads. Those reads must not
	 * move the start time.
	 *
	 * @param string $type Plugin type.
	 */
	private function mark_start( string $type ): void {
		if ( ! isset( $this->started[ $type ] ) ) {
			$this->last = Clock::now();
		}
	}

	/**
	 * Adds a plugin's load time to the report.
	 *
	 * @param string $type Plugin type.
	 * @param string $file Full path of the plugin file.
	 */
	private function record( string $type, string $file ): void {
		$now = Clock::now();

		$this->started[ $type ] = true;

		add_report_entry(
			'plugins',
			plugin_basename( $file ),
			array(
				'type'     => $type,
				'start'    => ms_since_request_start( $this->last ),
				'duration' => seconds_to_ms( $now - $this->last ),
			)
		);

		$this->last = $now;
	}
}
