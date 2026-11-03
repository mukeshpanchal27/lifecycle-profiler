<?php
/**
 * The compare screen on Tools > Profiler.
 *
 * @package LifecycleProfiler
 */

namespace LifecycleProfiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Value of the "view" query argument for the compare screen.
 */
const COMPARE_VIEW = 'compare';

/**
 * Returns the title of each section on the compare screen.
 *
 * @return array<string, string> Section titles, by section name, in screen order.
 */
function get_compare_section_titles(): array {
	return array(
		'phases'         => __( 'Phases', 'lifecycle-profiler' ),
		'sources'        => __( 'Plugins, themes and core', 'lifecycle-profiler' ),
		'hooks'          => __( 'Hooks (self time)', 'lifecycle-profiler' ),
		'template'       => __( 'Template', 'lifecycle-profiler' ),
		'template_parts' => __( 'Template parts', 'lifecycle-profiler' ),
		'blocks'         => __( 'Blocks (self time)', 'lifecycle-profiler' ),
	);
}

/**
 * Renders the compare screen.
 *
 * The "base" and "target" query arguments pick the two reports. Without them,
 * the screen compares the second newest report with the newest.
 *
 * @param array<string, array<string, mixed>> $index Index entries, newest first.
 */
function render_compare_screen( array $index ): void {
	$ids = array_keys( $index );

	// Only reads which reports to show.
	$base_id   = isset( $_GET['base'] ) ? sanitize_key( wp_unslash( $_GET['base'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$target_id = isset( $_GET['target'] ) ? sanitize_key( wp_unslash( $_GET['target'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! isset( $index[ $base_id ] ) ) {
		$base_id = (string) ( $ids[1] ?? '' );
	}

	if ( ! isset( $index[ $target_id ] ) ) {
		$target_id = (string) ( $ids[0] ?? '' );
	}

	$base   = '' !== $base_id ? get_saved_report( $base_id ) : null;
	$target = '' !== $target_id ? get_saved_report( $target_id ) : null;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Compare reports', 'lifecycle-profiler' ); ?></h1>
		<p><a href="<?php echo esc_url( get_admin_page_url() ); ?>"><?php esc_html_e( '&larr; Back to reports', 'lifecycle-profiler' ); ?></a></p>

		<?php if ( null === $base || null === $target ) : ?>
			<p><?php esc_html_e( 'Save at least two reports to compare them.', 'lifecycle-profiler' ); ?></p>
		</div>
			<?php
			return;
		endif;
		?>

		<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( ADMIN_PAGE_SLUG ); ?>" />
			<input type="hidden" name="view" value="<?php echo esc_attr( COMPARE_VIEW ); ?>" />
			<?php render_report_select( 'base', __( 'Report A', 'lifecycle-profiler' ), $index, $base_id ); ?>
			<?php render_report_select( 'target', __( 'Report B', 'lifecycle-profiler' ), $index, $target_id ); ?>
			<?php submit_button( __( 'Compare', 'lifecycle-profiler' ), 'secondary', '', false ); ?>
		</form>

		<?php render_compare_summary( $index[ $base_id ], $index[ $target_id ] ); ?>

		<?php
		$sections = compare_reports( $base, $target );

		foreach ( get_compare_section_titles() as $section => $title ) {
			render_compare_table( $title, $sections[ $section ] ?? array() );
		}
		?>
	</div>
	<?php
}

/**
 * Renders a list that picks a saved report.
 *
 * @param string                              $name     Field name.
 * @param string                              $label    Field label.
 * @param array<string, array<string, mixed>> $index    Index entries.
 * @param string                              $selected ID of the selected report.
 */
function render_report_select( string $name, string $label, array $index, string $selected ): void {
	?>
	<label for="lifecycle-profiler-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
	<select id="lifecycle-profiler-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>">
		<?php foreach ( $index as $id => $entry ) : ?>
			<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $selected ); ?>>
				<?php echo esc_html( get_report_title( $entry ) . ' (' . wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $entry['time'] ?? 0 ) ) . ')' ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Renders the request details of both reports.
 *
 * @param array<string, mixed> $base   Index entry of report A.
 * @param array<string, mixed> $target Index entry of report B.
 */
function render_compare_summary( array $base, array $target ): void {
	?>
	<table class="widefat striped" style="margin-top:1em">
		<thead>
			<tr>
				<th scope="col"></th>
				<th scope="col"><?php esc_html_e( 'Report A', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Report B', 'lifecycle-profiler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Report', 'lifecycle-profiler' ); ?></th>
				<td><?php echo esc_html( get_report_title( $base ) ); ?></td>
				<td><?php echo esc_html( get_report_title( $target ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'URL', 'lifecycle-profiler' ); ?></th>
				<td><code><?php echo esc_html( trim( ( $base['method'] ?? '' ) . ' ' . ( $base['url'] ?? '' ) ) ); ?></code></td>
				<td><code><?php echo esc_html( trim( ( $target['method'] ?? '' ) . ' ' . ( $target['url'] ?? '' ) ) ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Date', 'lifecycle-profiler' ); ?></th>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $base['time'] ?? 0 ) ) ); ?></td>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $target['time'] ?? 0 ) ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Total (ms)', 'lifecycle-profiler' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( (float) ( $base['total_time'] ?? 0 ), 2 ) ); ?></td>
				<td><?php echo esc_html( number_format_i18n( (float) ( $target['total_time'] ?? 0 ), 2 ) ); ?></td>
			</tr>
		</tbody>
	</table>
	<?php
}

/**
 * Renders one section of the comparison.
 *
 * Shows nothing if neither report has rows in the section.
 *
 * @param string                                                                    $title Section title.
 * @param array<string, array{label: string, base: float|null, target: float|null}> $rows  Rows from compare_reports().
 */
function render_compare_table( string $title, array $rows ): void {
	if ( array() === $rows ) {
		return;
	}
	?>
	<h2><?php echo esc_html( $title ); ?></h2>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Name', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Report A (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Report B (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Change (ms)', 'lifecycle-profiler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $row['label'] ); ?></code></td>
					<td><?php echo esc_html( format_compare_time( $row['base'] ) ); ?></td>
					<td><?php echo esc_html( format_compare_time( $row['target'] ) ); ?></td>
					<td><?php render_compare_change( $row ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Renders the change of one row, with the percentage when report A has a time.
 *
 * Slower is red and faster is green. A change under 0.01 ms shows as 0.
 *
 * @param array{label: string, base: float|null, target: float|null, change: float} $row Row from compare_reports().
 */
function render_compare_change( array $row ): void {
	$change = round( $row['change'], 2 );

	if ( 0.0 === $change ) {
		echo esc_html( number_format_i18n( 0, 2 ) );
		return;
	}

	$text = ( $change > 0 ? '+' : '−' ) . number_format_i18n( abs( $change ), 2 );

	if ( null !== $row['base'] && null !== $row['target'] && $row['base'] > 0 ) {
		$text .= ' (' . ( $change > 0 ? '+' : '−' ) . number_format_i18n( 100 * abs( $change ) / $row['base'], 1 ) . '%)';
	}

	printf(
		'<span style="color:%1$s">%2$s</span>',
		esc_attr( $change > 0 ? '#b32d2e' : '#007017' ),
		esc_html( $text )
	);
}

/**
 * Formats a time on the compare screen.
 *
 * @param float|null $time Time in milliseconds, or null if the report has no such row.
 * @return string Formatted time, or a dash.
 */
function format_compare_time( ?float $time ): string {
	return null === $time ? '—' : number_format_i18n( $time, 2 );
}
