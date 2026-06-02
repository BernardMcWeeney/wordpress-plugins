<?php
/**
 * Featured image fallback assignment.
 *
 * @package Greenberry
 */

namespace Greenberry\CategoryFeaturedImage;

defined( 'ABSPATH' ) || exit;

/**
 * Assigns configured default featured images to saved posts.
 */
class Assigner {
	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Post IDs queued for end-of-request assignment.
	 *
	 * @var array<int,bool>
	 */
	private $queued_post_ids = array();

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_after_insert_post', array( $this, 'queue_assignment' ), 20, 4 );
		add_action( 'init', array( $this, 'register_rest_hooks' ), 99 );
		add_action( 'shutdown', array( $this, 'process_queue' ) );
	}

	/**
	 * Registers REST hooks after public post types have been registered.
	 *
	 * @return void
	 */
	public function register_rest_hooks() {
		foreach ( array_keys( $this->settings->get_assignable_post_types() ) as $post_type ) {
			add_action( 'rest_after_insert_' . $post_type, array( $this, 'assign_after_rest_insert' ), 20, 3 );
		}
	}

	/**
	 * Queues a post after WordPress has inserted or updated it.
	 *
	 * @param int           $post_id     Post ID.
	 * @param \WP_Post      $post        Post object.
	 * @param bool          $update      Whether this was an update.
	 * @param \WP_Post|null $post_before Previous post object.
	 * @return void
	 */
	public function queue_assignment( $post_id, $post, $update, $post_before ) {
		unset( $update, $post_before );

		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );
		if ( ! $post || ! $this->is_supported_post( $post ) ) {
			return;
		}

		$this->queued_post_ids[ absint( $post_id ) ] = true;
	}

	/**
	 * Processes queued posts once the editor request has finished saving terms and meta.
	 *
	 * @return void
	 */
	public function process_queue() {
		if ( empty( $this->queued_post_ids ) ) {
			return;
		}

		foreach ( array_keys( $this->queued_post_ids ) as $post_id ) {
			$this->maybe_assign_featured_image( $post_id );
		}
	}

	/**
	 * Assigns a fallback before REST responses are prepared for the block editor.
	 *
	 * @param \WP_Post        $post     Inserted or updated post.
	 * @param \WP_REST_Request $request REST request.
	 * @param bool            $creating Whether the post was created.
	 * @return void
	 */
	public function assign_after_rest_insert( $post, $request, $creating ) {
		unset( $request, $creating );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$this->maybe_assign_featured_image( $post->ID );
	}

	/**
	 * Assigns a default featured image when a post still has none.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function maybe_assign_featured_image( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! $this->should_assign( $post ) ) {
			return;
		}

		$image_id = $this->settings->get_default_image_id_for_post( $post );
		if ( ! $image_id ) {
			return;
		}

		set_post_thumbnail( $post->ID, $image_id );
	}

	/**
	 * Checks if the post should receive a fallback image.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool
	 */
	private function should_assign( \WP_Post $post ) {
		if ( ! $this->is_supported_post( $post ) ) {
			return false;
		}

		if ( has_post_thumbnail( $post->ID ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether this post type/status can be handled.
	 *
	 * @param \WP_Post $post Post object.
	 * @return bool
	 */
	private function is_supported_post( \WP_Post $post ) {
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return false;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) {
			return false;
		}

		return post_type_supports( $post->post_type, 'thumbnail' );
	}
}
