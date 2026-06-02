<?php
/**
 * Stats data access.
 *
 * @package Greenberry
 */

namespace Greenberry\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Stores aggregate page-view counts.
 */
class Repository {
	const DB_VERSION_OPTION = 'greenberry_stats_db_version';
	const META_TOTAL        = '_greenberry_stats_views_total';

	/**
	 * Returns the daily stats table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;

		return $wpdb->prefix . 'greenberry_stats_daily';
	}

	/**
	 * Creates or updates the stats table.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $this->table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			view_date date NOT NULL,
			views bigint(20) unsigned NOT NULL DEFAULT 0,
			last_viewed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_date (post_id, view_date),
			KEY view_date (view_date),
			KEY post_id (post_id),
			KEY views (views)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, GREENBERRY_VERSION, false );
	}

	/**
	 * Records a single public view for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function record_view( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		$table     = $this->table();
		$view_date = current_datetime()->format( 'Y-m-d' );
		$now       = current_time( 'mysql' );

		$recorded = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, view_date, views, last_viewed_at)
				VALUES (%d, %s, 1, %s)
				ON DUPLICATE KEY UPDATE views = views + 1, last_viewed_at = %s",
				$post_id,
				$view_date,
				$now,
				$now
			)
		);

		if ( false === $recorded ) {
			return false;
		}

		$this->increment_total_views( $post_id );

		return true;
	}

	/**
	 * Gets the stored total views for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public function get_total_views( $post_id ) {
		return absint( get_post_meta( absint( $post_id ), self::META_TOTAL, true ) );
	}

	/**
	 * Gets total views recorded in the aggregate table.
	 *
	 * @return int
	 */
	public function get_all_time_views() {
		global $wpdb;

		return absint( $wpdb->get_var( 'SELECT COALESCE(SUM(views), 0) FROM ' . $this->table() ) );
	}

	/**
	 * Gets total views between two inclusive dates.
	 *
	 * @param string $start_date Date in Y-m-d format.
	 * @param string $end_date   Date in Y-m-d format.
	 * @return int
	 */
	public function get_period_views( $start_date, $end_date ) {
		global $wpdb;

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					'SELECT COALESCE(SUM(views), 0) FROM ' . $this->table() . ' WHERE view_date BETWEEN %s AND %s',
					$this->sanitize_date( $start_date ),
					$this->sanitize_date( $end_date )
				)
			)
		);
	}

	/**
	 * Gets the number of content items with at least one recorded view.
	 *
	 * @return int
	 */
	public function get_tracked_post_count() {
		global $wpdb;

		return absint( $wpdb->get_var( 'SELECT COUNT(DISTINCT post_id) FROM ' . $this->table() ) );
	}

	/**
	 * Gets daily totals between two inclusive dates.
	 *
	 * @param string $start_date Date in Y-m-d format.
	 * @param string $end_date   Date in Y-m-d format.
	 * @return array<string,int>
	 */
	public function get_daily_totals( $start_date, $end_date ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT view_date, SUM(views) AS views FROM ' . $this->table() . ' WHERE view_date BETWEEN %s AND %s GROUP BY view_date ORDER BY view_date ASC',
				$this->sanitize_date( $start_date ),
				$this->sanitize_date( $end_date )
			)
		);

		$totals = array();
		foreach ( $rows as $row ) {
			$totals[ (string) $row->view_date ] = absint( $row->views );
		}

		return $totals;
	}

	/**
	 * Gets top posts for a date range.
	 *
	 * @param string $start_date Date in Y-m-d format.
	 * @param string $end_date   Date in Y-m-d format.
	 * @param int    $limit      Number of rows.
	 * @return array<int,object>
	 */
	public function get_top_posts( $start_date, $end_date, $limit = 10 ) {
		global $wpdb;

		$limit = max( 1, min( 50, absint( $limit ) ) );
		$table = $this->table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.post_id, SUM(d.views) AS views, p.post_title, p.post_type
				FROM {$table} d
				INNER JOIN {$wpdb->posts} p ON p.ID = d.post_id
				WHERE d.view_date BETWEEN %s AND %s
					AND p.post_status = 'publish'
				GROUP BY d.post_id, p.post_title, p.post_type
				ORDER BY views DESC
				LIMIT %d",
				$this->sanitize_date( $start_date ),
				$this->sanitize_date( $end_date ),
				$limit
			)
		);
	}

	/**
	 * Gets top posts across all recorded stats.
	 *
	 * @param int $limit Number of rows.
	 * @return array<int,object>
	 */
	public function get_top_posts_all_time( $limit = 10 ) {
		global $wpdb;

		$limit = max( 1, min( 50, absint( $limit ) ) );
		$table = $this->table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.post_id, SUM(d.views) AS views, p.post_title, p.post_type
				FROM {$table} d
				INNER JOIN {$wpdb->posts} p ON p.ID = d.post_id
				WHERE p.post_status = 'publish'
				GROUP BY d.post_id, p.post_title, p.post_type
				ORDER BY views DESC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Returns public post types that should receive stats columns and tracking.
	 *
	 * @return array<string,\WP_Post_Type>
	 */
	public function get_countable_post_types() {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		unset( $post_types['attachment'] );

		return array_filter(
			$post_types,
			static function ( $post_type ) {
				return ! empty( $post_type->publicly_queryable );
			}
		);
	}

	/**
	 * Checks whether a post type should be tracked.
	 *
	 * @param string $post_type Post type key.
	 * @return bool
	 */
	public function is_countable_post_type( $post_type ) {
		$post_types = $this->get_countable_post_types();

		return isset( $post_types[ $post_type ] );
	}

	/**
	 * Increments the denormalized all-time post total.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function increment_total_views( $post_id ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta}
				SET meta_value = CAST(meta_value AS UNSIGNED) + 1
				WHERE post_id = %d AND meta_key = %s",
				absint( $post_id ),
				self::META_TOTAL
			)
		);

		if ( 0 === $updated ) {
			$added = add_post_meta( absint( $post_id ), self::META_TOTAL, 1, true );

			if ( ! $added ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta}
						SET meta_value = CAST(meta_value AS UNSIGNED) + 1
						WHERE post_id = %d AND meta_key = %s",
						absint( $post_id ),
						self::META_TOTAL
					)
				);
			}
		}
	}

	/**
	 * Keeps date strings in the expected Y-m-d format.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	private function sanitize_date( $date ) {
		$date = preg_replace( '/[^0-9-]/', '', (string) $date );

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : current_datetime()->format( 'Y-m-d' );
	}
}
