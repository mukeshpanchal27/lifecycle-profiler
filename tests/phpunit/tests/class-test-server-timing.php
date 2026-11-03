<?php
/**
 * Tests for the Server-Timing header value.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use WP_UnitTestCase;

use function LifecycleProfiler\get_server_timing_header;

/**
 * Tests for includes/server-timing.php.
 *
 * The CLI cannot send or read real headers, so the end-to-end test checks the
 * sent header. These tests check the header value.
 *
 * @covers ::LifecycleProfiler\get_server_timing_header
 */
class Test_Server_Timing extends WP_UnitTestCase {

	/**
	 * Loads the file. Only the mu-plugin loader loads it outside tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once dirname( __DIR__, 3 ) . '/includes/server-timing.php';
	}

	/**
	 * No phases give an empty value, so no header is sent.
	 */
	public function test_no_phases() {
		$this->assertSame( '', get_server_timing_header( array() ) );
	}

	/**
	 * Each phase is one metric, in order, with three decimals.
	 */
	public function test_metrics_in_order() {
		$phases = array(
			'bootstrap'  => array( 'duration' => 4.4926 ),
			'mu_plugins' => array( 'duration' => 23 ),
		);

		$this->assertSame( 'lp-bootstrap;dur=4.493, lp-mu_plugins;dur=23.000', get_server_timing_header( $phases ) );
	}

	/**
	 * Characters that are not allowed in a metric name are removed.
	 */
	public function test_metric_names_are_tokens() {
		$phases = array( 'bad name;x=1, y' => array( 'duration' => 1 ) );

		$this->assertSame( 'lp-badnamex1y;dur=1.000', get_server_timing_header( $phases ) );
	}

	/**
	 * The decimal point is always ".", whatever the locale.
	 *
	 * Skipped where no locale changes the decimal point. The Alpine images of
	 * wp-env, for example, always use ".".
	 */
	public function test_decimal_point_ignores_locale() {
		$locale = setlocale( LC_NUMERIC, '0' );
		setlocale( LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'de' );

		try {
			if ( '1,5' !== sprintf( '%.1f', 1.5 ) ) {
				$this->markTestSkipped( 'No locale on this system uses a decimal comma.' );
			}

			$this->assertSame( 'lp-init;dur=1.500', get_server_timing_header( array( 'init' => array( 'duration' => 1.5 ) ) ) );
		} finally {
			setlocale( LC_NUMERIC, $locale );
		}
	}
}
