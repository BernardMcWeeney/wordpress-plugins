<?php
/**
 * Public page-view tracker.
 *
 * @package Greenberry
 */

namespace Greenberry\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Records aggregate views for singular public content.
 */
class Tracker {
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
	 * Registers tracking hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'template_redirect', array( $this, 'maybe_record_view' ), 20 );
	}

	/**
	 * Records a view when the current request is a public singular post.
	 *
	 * @return void
	 */
	public function maybe_record_view() {
		if ( $this->should_skip_request() || ! is_singular() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( 'publish' !== get_post_status( $post ) ) {
			return;
		}

		if ( ! $this->repository->is_countable_post_type( $post->post_type ) ) {
			return;
		}

		$this->repository->record_view( $post->ID );
	}

	/**
	 * Checks requests that should never be counted as content views.
	 *
	 * @return bool
	 */
	private function should_skip_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return is_preview() || is_feed() || is_robots() || is_trackback();
	}
}
