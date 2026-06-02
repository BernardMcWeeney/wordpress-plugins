<?php
/**
 * Stats admin screen and post-list columns.
 *
 * @package Greenberry
 */

namespace Greenberry\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Renders aggregate site stats.
 */
class Admin {
	const COLUMN_KEY = 'greenberry_stats_views';

	/**
	 * Stats repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Stats repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_post_columns' ) );
	}

	/**
	 * Registers Stats submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'greenberry',
			__( 'Stats', 'greenberry' ),
			__( 'Stats', 'greenberry' ),
			'manage_options',
			'greenberry-stats',
			array( $this, 'render' )
		);
	}

	/**
	 * Adds the views column to public content list tables.
	 *
	 * @return void
	 */
	public function register_post_columns() {
		foreach ( $this->repository->get_countable_post_types() as $post_type => $object ) {
			if ( 'post' === $post_type ) {
				add_filter( 'manage_posts_columns', array( $this, 'add_views_column' ), 10, 2 );
				add_action( 'manage_posts_custom_column', array( $this, 'render_views_column' ), 10, 2 );
				continue;
			}

			if ( 'page' === $post_type ) {
				add_filter( 'manage_pages_columns', array( $this, 'add_views_column' ) );
				add_action( 'manage_pages_custom_column', array( $this, 'render_views_column' ), 10, 2 );
				continue;
			}

			add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_views_column' ) );
			add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'render_views_column' ), 10, 2 );
		}
	}

	/**
	 * Inserts the views column after the title column.
	 *
	 * @param array<string,string> $columns   Existing columns.
	 * @param string               $post_type Optional post type from generic post hooks.
	 * @return array<string,string>
	 */
	public function add_views_column( $columns, $post_type = '' ) {
		if ( ! $this->is_current_column_screen( $post_type ) ) {
			return $columns;
		}

		if ( isset( $columns[ self::COLUMN_KEY ] ) ) {
			return $columns;
		}

		$reordered = array();
		$inserted  = false;

		foreach ( $columns as $key => $label ) {
			$reordered[ $key ] = $label;

			if ( 'title' === $key ) {
				$reordered[ self::COLUMN_KEY ] = __( 'Views', 'greenberry' );
				$inserted                      = true;
			}
		}

		if ( ! $inserted ) {
			$reordered[ self::COLUMN_KEY ] = __( 'Views', 'greenberry' );
		}

		return $reordered;
	}

	/**
	 * Renders the views column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_views_column( $column, $post_id ) {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $this->is_current_column_screen( $post_type ) || ! $this->repository->is_countable_post_type( $post_type ) ) {
			return;
		}

		echo esc_html( number_format_i18n( $this->repository->get_total_views( $post_id ) ) );
	}

	/**
	 * Checks whether the current list-table callback belongs to the intended post type.
	 *
	 * @param string $post_type Optional post type.
	 * @return bool
	 */
	private function is_current_column_screen( $post_type = '' ) {
		$post_type = (string) $post_type;

		if ( '' === $post_type && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! empty( $screen->post_type ) ) {
				$post_type = (string) $screen->post_type;
			}
		}

		$filter = current_filter();
		if ( in_array( $filter, array( 'manage_posts_columns', 'manage_posts_custom_column' ), true ) ) {
			return 'post' === $post_type;
		}

		if ( in_array( $filter, array( 'manage_pages_columns', 'manage_pages_custom_column' ), true ) ) {
			return 'page' === $post_type;
		}

		return '' === $post_type || $this->repository->is_countable_post_type( $post_type );
	}

	/**
	 * Renders the Stats page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$today       = current_datetime();
		$today_date  = $today->format( 'Y-m-d' );
		$week_start  = $today->modify( '-6 days' )->format( 'Y-m-d' );
		$today_views = $this->repository->get_period_views( $today_date, $today_date );
		$week_views  = $this->repository->get_period_views( $week_start, $today_date );

		\Greenberry\Admin_UI::open(
			__( 'Stats', 'greenberry' ),
			__( 'Simple aggregate view counts for posts and pages.', 'greenberry' ),
			'greenberry-stats-admin'
		);
		?>
		<div class="greenberry-stats-kpis" aria-label="<?php esc_attr_e( 'Stats summary', 'greenberry' ); ?>">
			<?php $this->render_kpi( __( 'Today', 'greenberry' ), $today_views, __( 'Views recorded since midnight.', 'greenberry' ) ); ?>
			<?php $this->render_kpi( __( 'Last 7 days', 'greenberry' ), $week_views, __( 'Rolling weekly total.', 'greenberry' ) ); ?>
			<?php $this->render_kpi( __( 'All time', 'greenberry' ), $this->repository->get_all_time_views(), __( 'Total recorded views.', 'greenberry' ) ); ?>
		</div>

		<div class="greenberry-grid greenberry-grid--stats">
			<section class="greenberry-panel">
				<h2><?php esc_html_e( 'Daily Summary', 'greenberry' ); ?></h2>
				<p class="greenberry-muted"><?php esc_html_e( 'Total views by day for the last week.', 'greenberry' ); ?></p>
				<?php $this->render_daily_summary( $week_start, $today_date ); ?>
			</section>

			<div class="greenberry-stats-top">
				<section class="greenberry-panel">
					<h2><?php esc_html_e( 'Top 10 Today', 'greenberry' ); ?></h2>
					<?php $this->render_top_posts_table( $this->repository->get_top_posts( $today_date, $today_date, 10 ) ); ?>
				</section>

				<section class="greenberry-panel">
					<h2><?php esc_html_e( 'Top 10 Last 7 Days', 'greenberry' ); ?></h2>
					<?php $this->render_top_posts_table( $this->repository->get_top_posts( $week_start, $today_date, 10 ) ); ?>
				</section>

				<section class="greenberry-panel">
					<h2><?php esc_html_e( 'Top 10 All Time', 'greenberry' ); ?></h2>
					<?php $this->render_top_posts_table( $this->repository->get_top_posts_all_time( 10 ) ); ?>
				</section>
			</div>
		</div>

		<p class="greenberry-muted greenberry-stats-privacy">
			<?php esc_html_e( 'Greenberry Stats stores aggregate counts only. It does not store visitor IP addresses, user agents, or personal profiles.', 'greenberry' ); ?>
		</p>
		<?php
		\Greenberry\Admin_UI::close();
	}

	/**
	 * Renders one KPI tile.
	 *
	 * @param string $label Label.
	 * @param int    $value Value.
	 * @param string $help  Helper text.
	 * @return void
	 */
	private function render_kpi( $label, $value, $help = '' ) {
		?>
		<div class="greenberry-stats-kpi">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></strong>
			<?php if ( '' !== $help ) : ?>
				<small><?php echo esc_html( $help ); ?></small>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the daily totals list.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return void
	 */
	private function render_daily_summary( $start_date, $end_date ) {
		$totals     = $this->repository->get_daily_totals( $start_date, $end_date );
		$days       = $this->build_date_range( $start_date, $end_date );
		$max_views  = 0;
		$day_values = array();

		foreach ( $days as $date ) {
			$views              = isset( $totals[ $date ] ) ? absint( $totals[ $date ] ) : 0;
			$day_values[ $date ] = $views;
			$max_views          = max( $max_views, $views );
		}

		?>
		<ul class="greenberry-stats-days">
			<?php foreach ( $day_values as $date => $views ) : ?>
				<?php
				$date_object = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
				$timestamp   = $date_object ? $date_object->getTimestamp() : current_time( 'timestamp' );
				$width       = $max_views ? round( ( $views / $max_views ) * 100, 2 ) : 0;
				?>
				<li>
					<span class="greenberry-stats-days__date"><?php echo esc_html( wp_date( 'D j M', $timestamp ) ); ?></span>
					<span class="greenberry-stats-days__bar"><span style="<?php echo esc_attr( '--gb-stat-width: ' . $width . '%;' ); ?>"></span></span>
					<strong><?php echo esc_html( number_format_i18n( $views ) ); ?></strong>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renders a top-posts table.
	 *
	 * @param array<int,object> $rows Rows.
	 * @return void
	 */
	private function render_top_posts_table( $rows ) {
		if ( empty( $rows ) ) {
			echo '<div class="greenberry-stats-empty">';
			echo '<strong>' . esc_html__( 'No views yet', 'greenberry' ) . '</strong>';
			echo '<span>' . esc_html__( 'This list will fill automatically once published posts or pages receive visits.', 'greenberry' ) . '</span>';
			echo '</div>';
			return;
		}
		?>
		<table class="widefat striped greenberry-stats-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page', 'greenberry' ); ?></th>
					<th><?php esc_html_e( 'Type', 'greenberry' ); ?></th>
					<th><?php esc_html_e( 'Views', 'greenberry' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$post_id   = absint( $row->post_id );
					$edit_link = get_edit_post_link( $post_id, 'raw' );
					$title     = '' !== $row->post_title ? $row->post_title : __( '(untitled)', 'greenberry' );
					$type      = get_post_type_object( $row->post_type );
					?>
					<tr>
						<td>
							<strong>
								<?php if ( $edit_link ) : ?>
									<a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $title ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $title ); ?>
								<?php endif; ?>
							</strong>
							<div class="greenberry-stats-table__links">
								<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'greenberry' ); ?></a>
							</div>
						</td>
						<td><?php echo esc_html( $type ? $type->labels->singular_name : $row->post_type ); ?></td>
						<td><strong><?php echo esc_html( number_format_i18n( absint( $row->views ) ) ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Builds an inclusive date range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array<int,string>
	 */
	private function build_date_range( $start_date, $end_date ) {
		$dates    = array();
		$timezone = wp_timezone();
		$start    = new \DateTimeImmutable( $start_date, $timezone );
		$end      = new \DateTimeImmutable( $end_date, $timezone );

		while ( $start <= $end ) {
			$dates[] = $start->format( 'Y-m-d' );
			$start   = $start->modify( '+1 day' );
		}

		return $dates;
	}
}
