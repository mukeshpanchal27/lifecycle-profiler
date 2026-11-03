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
 * Returns the URL of the report screen.
 *
 * @param array<string, string> $args Extra query arguments.
 * @return string Screen URL.
 */
function get_admin_page_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => ADMIN_PAGE_SLUG ), $args ), admin_url( 'tools.php' ) );
}

/**
 * Renders the report screen.
 *
 * Shows the saved reports, then the report picked in the "report" query
 * argument, or the newest report. With "view=compare", shows the compare
 * screen instead.
 */
function render_admin_page(): void {
	$index = get_report_index();

	// Only reads which screen to show.
	if ( isset( $_GET['view'] ) && COMPARE_VIEW === $_GET['view'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		render_compare_screen( $index );
		return;
	}

	// Only reads which report to show.
	$id = isset( $_GET['report'] ) ? sanitize_key( wp_unslash( $_GET['report'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! isset( $index[ $id ] ) ) {
		$id = (string) get_latest_report_id();
	}

	$report = '' !== $id ? get_saved_report( $id ) : null;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Lifecycle Profiler', 'lifecycle-profiler' ); ?></h1>

		<?php if ( null === $report ) : ?>
			<p><?php esc_html_e( 'No report yet. Click "Profiler: OFF" in the admin bar to start a session, then load the page you want to profile.', 'lifecycle-profiler' ); ?></p>
		<?php else : ?>
			<?php render_report_list( $index, $id ); ?>
			<h2><?php echo esc_html( get_report_title( $index[ $id ] ) ); ?></h2>
			<?php render_report_meta( $report['meta'] ?? array() ); ?>
			<?php render_source_table( get_source_totals( $report ) ); ?>
			<?php render_phase_table( $report['phases'] ?? array(), (float) ( $report['meta']['total_time'] ?? 0 ) ); ?>
			<?php render_template_section( $report ); ?>
			<?php render_span_table( $report['spans'] ?? array() ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders the list of saved reports.
 *
 * @param array<string, array<string, mixed>> $index      Index entries, newest first.
 * @param string                              $current_id ID of the report on screen.
 */
function render_report_list( array $index, string $current_id ): void {
	?>
	<h2><?php esc_html_e( 'Saved reports', 'lifecycle-profiler' ); ?></h2>
	<p>
		<?php
		printf(
			/* translators: %s: Number of reports. */
			esc_html__( 'The profiler keeps the newest %s reports and deletes older ones.', 'lifecycle-profiler' ),
			esc_html( number_format_i18n( get_max_reports() ) )
		);
		?>
		<?php if ( count( $index ) > 1 ) : ?>
			<a class="button" href="<?php echo esc_url( get_admin_page_url( array( 'view' => COMPARE_VIEW ) ) ); ?>"><?php esc_html_e( 'Compare reports', 'lifecycle-profiler' ); ?></a>
		<?php endif; ?>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Label', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'URL', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Date', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Total (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'lifecycle-profiler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $index as $id => $entry ) : ?>
				<tr>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( LABEL_ACTION ); ?>" />
							<input type="hidden" name="report" value="<?php echo esc_attr( $id ); ?>" />
							<?php wp_nonce_field( get_report_nonce_action( LABEL_ACTION, $id ) ); ?>
							<label class="screen-reader-text" for="lifecycle-profiler-label-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Label', 'lifecycle-profiler' ); ?></label>
							<input type="text" id="lifecycle-profiler-label-<?php echo esc_attr( $id ); ?>" name="label" value="<?php echo esc_attr( $entry['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'No label', 'lifecycle-profiler' ); ?>" />
							<?php submit_button( __( 'Save label', 'lifecycle-profiler' ), 'small', 'submit', false ); ?>
						</form>
					</td>
					<td><code><?php echo esc_html( trim( ( $entry['method'] ?? '' ) . ' ' . ( $entry['url'] ?? '' ) ) ); ?></code></td>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $entry['time'] ?? 0 ) ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) ( $entry['total_time'] ?? 0 ), 1 ) ); ?></td>
					<td>
						<?php if ( $id === $current_id ) : ?>
							<strong><?php esc_html_e( 'Showing', 'lifecycle-profiler' ); ?></strong>
						<?php else : ?>
							<a href="<?php echo esc_url( get_admin_page_url( array( 'report' => $id ) ) ); ?>"><?php esc_html_e( 'View', 'lifecycle-profiler' ); ?></a>
						<?php endif; ?>
						| <a href="<?php echo esc_url( get_export_url( $id ) ); ?>"><?php esc_html_e( 'Download JSON', 'lifecycle-profiler' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return window.confirm( '<?php echo esc_js( __( 'Delete this report?', 'lifecycle-profiler' ) ); ?>' );">
							<input type="hidden" name="action" value="<?php echo esc_attr( DELETE_ACTION ); ?>" />
							<input type="hidden" name="report" value="<?php echo esc_attr( $id ); ?>" />
							<?php wp_nonce_field( get_report_nonce_action( DELETE_ACTION, $id ) ); ?>
							<?php submit_button( __( 'Delete', 'lifecycle-profiler' ), 'small link-delete', 'submit', false ); ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
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
			esc_html__( 'Request: %1$s, saved %2$s, total %3$s ms.', 'lifecycle-profiler' ),
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

/**
 * Renders the template, template parts and blocks of a report.
 *
 * @param array<string, mixed> $report Report data.
 */
function render_template_section( array $report ): void {
	$template = $report['template']['main'] ?? null;
	$parts    = sort_slowest_first( $report['template_parts'] ?? array(), 'total_time' );
	$blocks   = sort_slowest_first( $report['blocks'] ?? array(), 'self_time' );
	?>
	<h2><?php esc_html_e( 'Templates and blocks', 'lifecycle-profiler' ); ?></h2>

	<?php if ( null === $template && array() === $parts && array() === $blocks ) : ?>
		<p><?php esc_html_e( 'This request loaded no template and rendered no blocks.', 'lifecycle-profiler' ); ?></p>
		<?php
		return;
	endif;
	?>

	<?php if ( is_array( $template ) ) : ?>
		<p>
			<?php
			if ( ! empty( $template['block_template'] ) ) {
				printf(
					/* translators: 1: Block template ID, 2: Template file path, 3: Time in milliseconds. */
					esc_html__( 'Template: %1$s, loaded by %2$s, %3$s ms.', 'lifecycle-profiler' ),
					'<code>' . esc_html( $template['block_template'] ) . '</code>',
					'<code>' . esc_html( $template['file'] ?? '' ) . '</code>',
					esc_html( number_format_i18n( (float) ( $template['duration'] ?? 0 ), 2 ) )
				);
			} else {
				printf(
					/* translators: 1: Template file path, 2: Time in milliseconds. */
					esc_html__( 'Template: %1$s, %2$s ms.', 'lifecycle-profiler' ),
					'<code>' . esc_html( $template['file'] ?? '' ) . '</code>',
					esc_html( number_format_i18n( (float) ( $template['duration'] ?? 0 ), 2 ) )
				);
			}
			?>
			<br />
			<span class="description"><?php esc_html_e( 'The template time includes every hook that fires while the template runs, such as wp_head and wp_footer.', 'lifecycle-profiler' ); ?></span>
		</p>
	<?php endif; ?>

	<?php if ( array() !== $parts ) : ?>
		<h3><?php esc_html_e( 'Template parts', 'lifecycle-profiler' ); ?></h3>
		<p><?php esc_html_e( 'Slowest first. The time of a part includes the parts nested inside it.', 'lifecycle-profiler' ); ?></p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Template part', 'lifecycle-profiler' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'lifecycle-profiler' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Total (ms)', 'lifecycle-profiler' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Calls', 'lifecycle-profiler' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $parts as $part ) : ?>
					<tr>
						<td><code><?php echo esc_html( $part['name'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( 'block' === ( $part['type'] ?? '' ) ? __( 'Block', 'lifecycle-profiler' ) : __( 'File', 'lifecycle-profiler' ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (float) $part['total_time'], 2 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $part['calls'] ?? 0 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( array() !== $blocks ) : ?>
		<h3><?php esc_html_e( 'Blocks', 'lifecycle-profiler' ); ?></h3>
		<p><?php esc_html_e( 'Slowest self time first. Self time leaves out inner blocks. Total time includes them.', 'lifecycle-profiler' ); ?></p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Block type', 'lifecycle-profiler' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Self (ms)', 'lifecycle-profiler' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Total (ms)', 'lifecycle-profiler' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Calls', 'lifecycle-profiler' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $blocks as $block ) : ?>
					<tr>
						<td><code><?php echo esc_html( $block['name'] ?? '' ); ?></code></td>
						<td><strong><?php echo esc_html( number_format_i18n( (float) $block['self_time'], 2 ) ); ?></strong></td>
						<td><?php echo esc_html( number_format_i18n( (float) ( $block['total_time'] ?? 0 ), 2 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) ( $block['calls'] ?? 0 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}

/**
 * Sorts report entries by a time field, slowest first.
 *
 * Entries without the field are left out.
 *
 * @param array<string, array<string, mixed>> $entries Report entries.
 * @param string                              $field   Time field, such as "total_time".
 * @return array<int, array<string, mixed>> Sorted entries.
 */
function sort_slowest_first( array $entries, string $field ): array {
	$entries = array_values(
		array_filter(
			$entries,
			static function ( $entry ) use ( $field ): bool {
				return is_array( $entry ) && isset( $entry[ $field ] );
			}
		)
	);

	usort(
		$entries,
		static function ( array $a, array $b ) use ( $field ): int {
			return (float) $b[ $field ] <=> (float) $a[ $field ];
		}
	);

	return $entries;
}

/**
 * Renders load time and hook time for each source, slowest first.
 *
 * @param array<int, array<string, mixed>> $rows Rows from get_source_totals().
 */
function render_source_table( array $rows ): void {
	?>
	<h2><?php esc_html_e( 'Plugins, themes and core', 'lifecycle-profiler' ); ?></h2>
	<p><?php esc_html_e( 'Load time is the time to include the plugin files. Hook time is the self time of the source\'s hook callbacks.', 'lifecycle-profiler' ); ?></p>

	<?php if ( array() === $rows ) : ?>
		<p><?php esc_html_e( 'This report has no plugin or hook times.', 'lifecycle-profiler' ); ?></p>
		<?php
		return;
	endif;
	?>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Source', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Load time (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Hook time (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Total (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Callbacks', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Calls', 'lifecycle-profiler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( get_source_label( $row['type'], $row['name'] ) ); ?></strong><br />
						<span class="description"><?php echo esc_html( $row['type'] . ': ' . $row['name'] ); ?></span>
					</td>
					<td><?php echo esc_html( number_format_i18n( (float) $row['load_time'], 2 ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $row['hook_time'], 2 ) ); ?></td>
					<td><strong><?php echo esc_html( number_format_i18n( (float) $row['total_time'], 2 ) ); ?></strong></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['callbacks'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['calls'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Returns a display name for a source.
 *
 * @param string $type Source type.
 * @param string $name Source name: a folder or file name.
 * @return string Plugin or theme name if known, otherwise the source name.
 */
function get_source_label( string $type, string $name ): string {
	static $plugins = null;

	if ( 'plugin' === $type ) {
		if ( null === $plugins ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
		}

		foreach ( $plugins as $basename => $data ) {
			if ( strtok( $basename, '/' ) === $name && ! empty( $data['Name'] ) ) {
				return $data['Name'];
			}
		}
	}

	if ( 'theme' === $type ) {
		$theme = wp_get_theme( $name );

		if ( $theme->exists() ) {
			return $theme->get( 'Name' );
		}
	}

	return $name;
}

/**
 * Renders custom spans from the timing API.
 *
 * Shows nothing if the report has no spans.
 *
 * @param array<string, array<string, mixed>> $spans Span entries.
 */
function render_span_table( array $spans ): void {
	if ( array() === $spans ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'Custom spans', 'lifecycle-profiler' ); ?></h2>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Span', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'First start (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Total (ms)', 'lifecycle-profiler' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Calls', 'lifecycle-profiler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $spans as $name => $span ) : ?>
				<tr>
					<td><code><?php echo esc_html( $name ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( (float) $span['start'], 2 ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $span['total_time'], 2 ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $span['calls'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
