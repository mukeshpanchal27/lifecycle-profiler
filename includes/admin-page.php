<?php
/**
 * The Tools > Profiler screen.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu slug of the report screen.
 */
const ADMIN_PAGE_SLUG = 'lifecycle-profiler';

/**
 * Adds the Tools > Profiler screen.
 */
function add_admin_page(): void {
	add_management_page(
		__( 'Lifecycle Profiler', 'lifecycle-profiler' ),
		__( 'Profiler', 'lifecycle-profiler' ),
		SESSION_CAPABILITY,
		ADMIN_PAGE_SLUG,
		__NAMESPACE__ . '\render_admin_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_admin_page' );

/**
 * Stops the report screen from replacing the report it shows.
 *
 * @param bool $save Whether to save the report.
 * @return bool False on the report screen, otherwise unchanged.
 */
function skip_saving_admin_page( $save ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( $screen && 'tools_page_' . ADMIN_PAGE_SLUG === $screen->id ) {
		return false;
	}

	return $save;
}
add_filter( 'lifecycle_profiler_save_report', __NAMESPACE__ . '\skip_saving_admin_page' );

/**
 * Renders the report screen.
 */
function render_admin_page(): void {
	$report = get_latest_report();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Lifecycle Profiler', 'lifecycle-profiler' ); ?></h1>

		<?php if ( null === $report ) : ?>
			<p><?php esc_html_e( 'No report yet. Click "Profiler: OFF" in the admin bar to start a session, then load the page you want to profile.', 'lifecycle-profiler' ); ?></p>
		<?php else : ?>
			<?php render_report_meta( $report['meta'] ?? array() ); ?>
			<?php render_phase_table( $report['phases'] ?? array(), (float) ( $report['meta']['total_time'] ?? 0 ) ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders the request details of a report.
 *
 * @param array<string, mixed> $meta Report meta.
 */
function render_report_meta( array $meta ): void {
	?>
	<p>
		<?php
		printf(
			/* translators: 1: Request method and URL, 2: Date and time, 3: Total time in milliseconds. */
			esc_html__( 'Latest report: %1$s, saved %2$s, total %3$s ms.', 'lifecycle-profiler' ),
			'<code>' . esc_html( trim( ( $meta['method'] ?? '' ) . ' ' . ( $meta['url'] ?? '' ) ) ) . '</code>',
			esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $meta['time'] ?? 0 ) ) ),
			esc_html( number_format_i18n( (float) ( $meta['total_time'] ?? 0 ), 1 ) )
		);
		?>
	</p>
	<?php
}

/**
 * Renders the phase table.
 *
 * @param array<string, array<string, mixed>> $phases     Phase entries.
 * @param float                               $total_time Total request time in milliseconds.
 */
function render_phase_table( array $phases, float $total_time ): void {
	?>
	<h2><?php esc_html_e( 'Phases', 'lifecycle-profiler' ); ?></h2>

	<?php if ( array() === $phases ) : ?>
		<p><?php esc_html_e( 'This report has no phase times.', 'lifecycle-profiler' ); ?></p>
		<?php
		return;
	endif;
	?>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Phase', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Start (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Duration (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Share of total', 'lifecycle-profiler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $phases as $name => $phase ) : ?>
				<tr>
					<td><code><?php echo esc_html( $name ); ?></code> &rarr; <code><?php echo esc_html( $phase['end'] ?? '' ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( (float) $phase['start'], 2 ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $phase['duration'], 2 ) ); ?></td>
					<td><?php echo esc_html( $total_time > 0 ? number_format_i18n( 100 * (float) $phase['duration'] / $total_time, 1 ) . '%' : '' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
