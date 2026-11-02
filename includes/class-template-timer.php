<?php
/**
 * Times the main template of a request.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records which template WordPress loads and how long it runs.
 *
 * WordPress includes the template right after the template_include filter.
 * Nothing runs between the end of the template and shutdown, so the template
 * time runs from the last template_include callback to the start of shutdown.
 * This time includes every hook that fires while the template runs, such as
 * wp_head and wp_footer.
 */
final class Template_Timer {

	/**
	 * Report section for the template.
	 */
	const SECTION = 'template';

	/**
	 * Full path of the template, or null before template_include runs.
	 *
	 * @var string|null
	 */
	private $file = null;

	/**
	 * When the template started, as microtime( true ).
	 *
	 * @var float
	 */
	private $start = 0.0;

	/**
	 * Adds the hooks. Call this from the mu-plugin loader.
	 */
	public function register(): void {
		// After every other callback, so the template is the final one.
		add_filter( 'template_include', array( $this, 'start' ), PHP_INT_MAX );
		add_action( 'shutdown', array( $this, 'record_template' ), PHP_INT_MIN, 0 );
	}

	/**
	 * Records the template and its start time.
	 *
	 * @param mixed $template Path of the template to include.
	 * @return mixed The same template path.
	 */
	public function start( $template ) {
		if ( is_string( $template ) && '' !== $template ) {
			$this->file  = wp_normalize_path( $template );
			$this->start = microtime( true );
		}

		return $template;
	}

	/**
	 * Adds the template to the report.
	 *
	 * For a block theme, the file is template-canvas.php, so the entry also
	 * names the block template, such as "twentytwentyfive//page".
	 */
	public function record_template(): void {
		if ( null === $this->file ) {
			return;
		}

		$block_template = $GLOBALS['_wp_current_template_id'] ?? null;

		add_report_entry(
			self::SECTION,
			'main',
			array(
				'file'           => get_relative_path( $this->file ),
				'block_template' => is_string( $block_template ) ? $block_template : null,
				'start'          => ms_since_request_start( $this->start ),
				'duration'       => seconds_to_ms( microtime( true ) - $this->start ),
			)
		);
	}
}
