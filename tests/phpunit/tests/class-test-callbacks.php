<?php
/**
 * Tests for callback names and sources.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use WP_UnitTestCase;

use function LifecycleProfiler\format_callback_location;
use function LifecycleProfiler\get_callback_location;
use function LifecycleProfiler\get_callback_name;
use function LifecycleProfiler\get_file_source;
use function LifecycleProfiler\is_own_callback;

/**
 * Tests for includes/callbacks.php.
 *
 * @covers ::LifecycleProfiler\get_callback_name
 * @covers ::LifecycleProfiler\is_own_callback
 * @covers ::LifecycleProfiler\get_callback_location
 * @covers ::LifecycleProfiler\get_file_source
 * @covers ::LifecycleProfiler\format_callback_location
 */
class Test_Callbacks extends WP_UnitTestCase {

	/**
	 * Loads the file. Only the mu-plugin loader loads it outside tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once dirname( __DIR__, 3 ) . '/includes/callbacks.php';
	}

	/**
	 * Returns file paths and the source each one belongs to.
	 *
	 * @return array<string, array{0: callable, 1: string, 2: string}> Path builder, type and name.
	 */
	public function data_file_sources() {
		return array(
			'plugin folder'      => array(
				static function () {
					return WP_PLUGIN_DIR . '/akismet/class.akismet.php';
				},
				'plugin',
				'akismet',
			),
			'plugin file'        => array(
				static function () {
					return WP_PLUGIN_DIR . '/hello.php';
				},
				'plugin',
				'hello.php',
			),
			'mu-plugin'          => array(
				static function () {
					return WPMU_PLUGIN_DIR . '/my-mu-plugin.php';
				},
				'mu-plugin',
				'my-mu-plugin.php',
			),
			'theme'              => array(
				static function () {
					return get_theme_root() . '/twentytwentyfive/functions.php';
				},
				'theme',
				'twentytwentyfive',
			),
			'drop-in'            => array(
				static function () {
					return WP_CONTENT_DIR . '/object-cache.php';
				},
				'dropin',
				'object-cache.php',
			),
			'core'               => array(
				static function () {
					return ABSPATH . WPINC . '/plugin.php';
				},
				'core',
				'WordPress',
			),
			'outside WP'         => array(
				static function () {
					return '/opt/elsewhere/code.php';
				},
				'other',
				'code.php',
			),
			'unknown file'       => array(
				static function () {
					return '';
				},
				'other',
				'Unknown',
			),
			'plugin folder only' => array(
				static function () {
					return WP_PLUGIN_DIR . '/';
				},
				'plugin',
				'',
			),
		);
	}

	/**
	 * Each file maps to the right source type and name.
	 *
	 * @dataProvider data_file_sources
	 *
	 * @param callable $path Returns the file path.
	 * @param string   $type Expected source type.
	 * @param string   $name Expected source name.
	 */
	public function test_file_source( $path, $type, $name ) {
		$file = $path();
		$file = '' === $file ? '' : wp_normalize_path( $file );

		$this->assertSame(
			array(
				'type' => $type,
				'name' => $name,
			),
			get_file_source( $file )
		);
	}

	/**
	 * Built-in PHP functions have no file.
	 */
	public function test_builtin_function_is_php() {
		$this->assertNull( get_callback_location( 'strlen' ) );
		$this->assertSame( 'php', get_file_source( null )['type'] );
		$this->assertSame( '', format_callback_location( null ) );
	}

	/**
	 * A core function points to its file and line in wp-includes.
	 */
	public function test_core_function_location() {
		$location = get_callback_location( 'wp_head' );

		$this->assertSame( 'core', get_file_source( $location['file'] )['type'] );
		$this->assertMatchesRegularExpression( '#^wp-includes/general-template\.php:\d+$#', format_callback_location( $location ) );
	}

	/**
	 * A closure and a static method point to the file that defines them.
	 */
	public function test_closure_and_method_location() {
		$closure = static function () {};

		foreach ( array( $closure, array( self::class, 'set_up_before_class' ), self::class . '::set_up_before_class', array( $this, 'test_closure_and_method_location' ) ) as $callback ) {
			$location = get_callback_location( $callback );

			$this->assertSame( wp_normalize_path( __FILE__ ), $location['file'] );
			$this->assertGreaterThan( 0, $location['line'] );
		}
	}

	/**
	 * Callbacks that cannot be looked up are "other", not PHP.
	 */
	public function test_unknown_callback_is_other() {
		foreach ( array( 'parent::method', 'self::method', array( 'No_Such_Class', 'method' ), 'no_such_function' ) as $callback ) {
			$location = get_callback_location( $callback );

			$this->assertSame( '', $location['file'] );
			$this->assertSame( 'other', get_file_source( $location['file'] )['type'] );
			$this->assertSame( '', format_callback_location( $location ) );
		}
	}

	/**
	 * Callback names follow the PHP callable syntax.
	 */
	public function test_callback_names() {
		$this->assertSame( 'wp_head', get_callback_name( 'wp_head' ) );
		$this->assertSame( self::class . '::method', get_callback_name( array( self::class, 'method' ) ) );
		$this->assertSame( self::class . '->method', get_callback_name( array( $this, 'method' ) ) );
		$this->assertSame( 'Closure', get_callback_name( static function () {} ) );
		$this->assertSame( 'Unknown', get_callback_name( 42 ) );
	}

	/**
	 * Only callbacks in the plugin namespace count as its own.
	 */
	public function test_own_callbacks() {
		$this->assertTrue( is_own_callback( 'LifecycleProfiler\save_report' ) );
		$this->assertTrue( is_own_callback( array( 'LifecycleProfiler\Hook_Timer', 'wrap_callbacks' ) ) );
		$this->assertFalse( is_own_callback( 'wp_head' ) );
		$this->assertFalse( is_own_callback( array( 'WP_Hook', 'add_filter' ) ) );
	}
}
