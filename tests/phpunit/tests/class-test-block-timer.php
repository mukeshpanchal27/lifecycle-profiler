<?php
/**
 * Tests for the block timer.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler\Tests;

use LifecycleProfiler\Block_Timer;
use WP_Block;
use WP_UnitTestCase;

use function LifecycleProfiler\get_report;

/**
 * Tests for includes/class-block-timer.php.
 *
 * @covers \LifecycleProfiler\Block_Timer
 */
class Test_Block_Timer extends WP_UnitTestCase {

	/**
	 * The timer under test.
	 *
	 * @var Block_Timer
	 */
	private $timer;

	/**
	 * Loads the files. Only the mu-plugin loader loads them outside tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once dirname( __DIR__, 3 ) . '/includes/class-clock.php';
		require_once dirname( __DIR__, 3 ) . '/includes/report.php';
		require_once dirname( __DIR__, 3 ) . '/includes/class-block-timer.php';
	}

	/**
	 * Starts a timer.
	 */
	public function set_up() {
		parent::set_up();

		$this->timer = new Block_Timer();
		$this->timer->register();
	}

	/**
	 * Removes the timer and the test blocks.
	 */
	public function tear_down() {
		remove_filter( 'pre_render_block', array( $this->timer, 'start' ), PHP_INT_MAX );
		remove_filter( 'interactivity_process_directives', array( $this->timer, 'enter_render' ), PHP_INT_MIN );
		remove_filter( 'render_block', array( $this->timer, 'stop' ), PHP_INT_MAX );
		remove_action( 'shutdown', array( $this->timer, 'record_blocks' ), PHP_INT_MAX - 10 );

		foreach ( array( 'lp-test/rerender', 'lp-test/child' ) as $name ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}

		parent::tear_down();
	}

	/**
	 * A block that renders itself again keeps one timer for the whole render.
	 *
	 * This is how core/comment-template renders each comment.
	 */
	public function test_block_that_renders_itself_again() {
		register_block_type(
			'lp-test/child',
			array(
				'render_callback' => static function () {
					usleep( 5000 );
					return 'child';
				},
			)
		);
		register_block_type(
			'lp-test/rerender',
			array(
				'skip_inner_blocks' => true,
				'render_callback'   => static function ( $attributes, $content, $block ) {
					$output = '';
					for ( $i = 0; $i < 3; $i++ ) {
						$output .= ( new WP_Block( $block->parsed_block ) )->render( array( 'dynamic' => false ) );
					}
					return $output;
				},
			)
		);

		$output = do_blocks( '<!-- wp:lp-test/rerender --><!-- wp:lp-test/child /--><!-- /wp:lp-test/rerender -->' );
		$this->timer->record_blocks();
		$blocks = get_report()['blocks'];

		$this->assertSame( 'childchildchild', $output );
		$this->assertSame( 1, $blocks['lp-test/rerender']['calls'], 'The parent block rendered once.' );
		$this->assertSame( 3, $blocks['lp-test/child']['calls'], 'The child block rendered once for each pass.' );
		$this->assertGreaterThanOrEqual( 15.0, $blocks['lp-test/rerender']['total_time'], 'The parent time includes all three passes.' );
		$this->assertEqualsWithDelta(
			$blocks['lp-test/rerender']['total_time'] - $blocks['lp-test/child']['total_time'],
			$blocks['lp-test/rerender']['self_time'],
			0.01,
			'The parent self time leaves out the child blocks.'
		);
	}
}
