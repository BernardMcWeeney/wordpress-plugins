<?php
/**
 * Newsletter campaign post type.
 *
 * Campaigns are designed in the native WordPress block editor and rendered to
 * email HTML when sent. Delivery settings (subject, preheader, list) live in a
 * meta box so they save alongside the post.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Campaign block-editor post type and its delivery settings.
 */
class Campaign_Post_Type {
	const POST_TYPE = 'greenberry_campaign';

	const META_SUBJECT    = '_greenberry_subject';
	const META_PREHEADER  = '_greenberry_preheader';
	const META_LIST_ID    = '_greenberry_list_id';
	const META_SENT_AT    = '_greenberry_sent_at';
	const META_SENT_COUNT = '_greenberry_sent_count';

	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
			add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_delivery_meta' ), 10, 2 );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Registers the campaign post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Campaigns', 'greenberry' ),
					'singular_name'      => __( 'Campaign', 'greenberry' ),
					'add_new'            => __( 'Add Campaign', 'greenberry' ),
					'add_new_item'       => __( 'Add Campaign', 'greenberry' ),
					'edit_item'          => __( 'Edit Campaign', 'greenberry' ),
					'new_item'           => __( 'New Campaign', 'greenberry' ),
					'view_item'          => __( 'Preview Campaign', 'greenberry' ),
					'search_items'       => __( 'Search Campaigns', 'greenberry' ),
					'not_found'          => __( 'No campaigns yet.', 'greenberry' ),
					'not_found_in_trash' => __( 'No campaigns in the bin.', 'greenberry' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'menu_icon'           => 'dashicons-email',
				'supports'            => array( 'title', 'editor', 'revisions' ),
			)
		);
	}

	/**
	 * Registers the delivery meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'greenberry-campaign-delivery',
			__( 'Email delivery', 'greenberry' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Renders the delivery meta box.
	 *
	 * @param \WP_Post $post Campaign post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'greenberry_campaign_delivery', 'greenberry_campaign_delivery_nonce' );

		$subject   = (string) get_post_meta( $post->ID, self::META_SUBJECT, true );
		$preheader = (string) get_post_meta( $post->ID, self::META_PREHEADER, true );
		$list_id   = absint( get_post_meta( $post->ID, self::META_LIST_ID, true ) );
		$sent_at   = (string) get_post_meta( $post->ID, self::META_SENT_AT, true );
		?>
		<p>
			<label for="greenberry-campaign-subject"><strong><?php esc_html_e( 'Subject', 'greenberry' ); ?></strong></label>
			<input type="text" id="greenberry-campaign-subject" name="greenberry_campaign_subject" class="widefat" value="<?php echo esc_attr( $subject ); ?>">
		</p>
		<?php Email_Template::render_placeholder_picker( 'greenberry-campaign-subject' ); ?>
		<p>
			<label for="greenberry-campaign-preheader"><strong><?php esc_html_e( 'Preheader', 'greenberry' ); ?></strong></label>
			<input type="text" id="greenberry-campaign-preheader" name="greenberry_campaign_preheader" class="widefat" value="<?php echo esc_attr( $preheader ); ?>">
			<span class="description"><?php esc_html_e( 'Short preview text shown in the inbox.', 'greenberry' ); ?></span>
		</p>
		<p>
			<label for="greenberry-campaign-list"><strong><?php esc_html_e( 'Send to list', 'greenberry' ); ?></strong></label>
			<select id="greenberry-campaign-list" name="greenberry_campaign_list_id" class="widefat">
				<option value="0"><?php esc_html_e( 'All subscribed contacts', 'greenberry' ); ?></option>
				<?php foreach ( $this->repository->get_lists() as $list ) : ?>
					<option value="<?php echo esc_attr( $list->id ); ?>" <?php selected( $list_id, absint( $list->id ) ); ?>><?php echo esc_html( $list->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php if ( '' !== $sent_at ) : ?>
				<?php
				printf(
					/* translators: %s: date sent. */
					esc_html__( 'Sent %s.', 'greenberry' ),
					esc_html( $sent_at )
				);
				?>
			<?php else : ?>
				<?php esc_html_e( 'Save the campaign, then send it from the Newsletter → Campaigns screen.', 'greenberry' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Saves delivery meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_delivery_meta( $post_id, $post ) {
		if ( ! isset( $_POST['greenberry_campaign_delivery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['greenberry_campaign_delivery_nonce'] ) ), 'greenberry_campaign_delivery' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta(
			$post_id,
			self::META_SUBJECT,
			isset( $_POST['greenberry_campaign_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['greenberry_campaign_subject'] ) ) : ''
		);
		update_post_meta(
			$post_id,
			self::META_PREHEADER,
			isset( $_POST['greenberry_campaign_preheader'] ) ? sanitize_text_field( wp_unslash( $_POST['greenberry_campaign_preheader'] ) ) : ''
		);
		update_post_meta(
			$post_id,
			self::META_LIST_ID,
			isset( $_POST['greenberry_campaign_list_id'] ) ? absint( wp_unslash( $_POST['greenberry_campaign_list_id'] ) ) : 0
		);
	}

	/**
	 * Adds admin list columns.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function columns( $columns ) {
		$reordered = array();

		foreach ( $columns as $key => $label ) {
			$reordered[ $key ] = $label;
			if ( 'title' === $key ) {
				$reordered['greenberry_subject'] = __( 'Subject', 'greenberry' );
				$reordered['greenberry_status']  = __( 'Status', 'greenberry' );
			}
		}

		return $reordered;
	}

	/**
	 * Renders custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		if ( 'greenberry_subject' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, self::META_SUBJECT, true ) );
			return;
		}

		if ( 'greenberry_status' === $column ) {
			$sent_at = (string) get_post_meta( $post_id, self::META_SENT_AT, true );
			echo '' !== $sent_at
				? esc_html( sprintf( /* translators: %s: date. */ __( 'Sent %s', 'greenberry' ), $sent_at ) )
				: esc_html__( 'Draft', 'greenberry' );
		}
	}
}
