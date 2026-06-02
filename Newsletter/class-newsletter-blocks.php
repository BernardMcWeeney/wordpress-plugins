<?php
/**
 * Newsletter blocks.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Newsletter signup block.
 */
class Blocks {
	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers dynamic blocks.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$block_dir = GREENBERRY_PLUGIN_DIR . 'Newsletter/block/newsletter-form';
		$block_url = GREENBERRY_PLUGIN_URL . 'Newsletter/block/newsletter-form/';

		wp_register_script(
			'greenberry-newsletter-form-editor',
			$block_url . 'editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			GREENBERRY_VERSION,
			true
		);

		wp_register_script(
			'greenberry-newsletter-form-view',
			$block_url . 'view.js',
			array(),
			GREENBERRY_VERSION,
			true
		);

		wp_register_style(
			'greenberry-newsletter-form',
			$block_url . 'style.css',
			array(),
			GREENBERRY_VERSION
		);

		register_block_type(
			$block_dir,
			array(
				'editor_script'   => 'greenberry-newsletter-form-editor',
				'view_script'     => 'greenberry-newsletter-form-view',
				'style'           => 'greenberry-newsletter-form',
				'render_callback' => array( $this, 'render_newsletter_form' ),
			)
		);

		$this->register_posts_block();
	}

	/**
	 * Registers the configurable "Latest Posts" email block.
	 *
	 * @return void
	 */
	private function register_posts_block() {
		$posts_block_dir = GREENBERRY_PLUGIN_DIR . 'Newsletter/block/newsletter-posts';
		$posts_block_url = GREENBERRY_PLUGIN_URL . 'Newsletter/block/newsletter-posts/';

		wp_register_script(
			'greenberry-newsletter-posts-editor',
			$posts_block_url . 'editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			GREENBERRY_VERSION,
			true
		);

		if ( is_admin() ) {
			wp_localize_script(
				'greenberry-newsletter-posts-editor',
				'greenberryNewsletterPosts',
				array(
					'postTypes'  => $this->get_post_type_options(),
					'categories' => $this->get_category_options(),
				)
			);
		}

		register_block_type(
			$posts_block_dir,
			array(
				'editor_script'   => 'greenberry-newsletter-posts-editor',
				'render_callback' => array( $this, 'render_newsletter_posts' ),
			)
		);
	}

	/**
	 * Returns public post types for the block editor select.
	 *
	 * @return array<int,array{label:string,value:string}>
	 */
	private function get_post_type_options() {
		$options = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$options[] = array(
				'label' => isset( $post_type->labels->singular_name ) && $post_type->labels->singular_name ? $post_type->labels->singular_name : $post_type->name,
				'value' => $post_type->name,
			);
		}

		return $options;
	}

	/**
	 * Returns categories for the block editor select.
	 *
	 * @return array<int,array{label:string,value:int}>
	 */
	private function get_category_options() {
		$options = array();

		foreach ( get_categories( array( 'hide_empty' => false, 'number' => 200 ) ) as $category ) {
			$options[] = array(
				'label' => $category->name,
				'value' => absint( $category->term_id ),
			);
		}

		return $options;
	}

	/**
	 * Renders the Latest Posts block as email-safe HTML.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_newsletter_posts( $attributes = array() ) {
		$attributes = wp_parse_args(
			(array) $attributes,
			array(
				'postType'    => 'post',
				'category'    => 0,
				'count'       => 5,
				'showImage'   => true,
				'showExcerpt' => true,
				'showButton'  => true,
			)
		);

		$post_type = sanitize_key( $attributes['postType'] );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			$post_type = 'post';
		}

		$query_args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 20, absint( $attributes['count'] ) ) ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		);

		$category = absint( $attributes['category'] );
		if ( $category && is_object_in_taxonomy( $post_type, 'category' ) ) {
			$query_args['cat'] = $category;
		}

		$posts = get_posts( $query_args );

		if ( empty( $posts ) ) {
			return current_user_can( 'edit_posts' )
				? '<p style="color:#646970;font-style:italic;">' . esc_html__( 'No matching posts yet. Published posts will appear here.', 'greenberry' ) . '</p>'
				: '';
		}

		return $this->render_posts_html(
			$posts,
			! empty( $attributes['showImage'] ),
			! empty( $attributes['showExcerpt'] ),
			! empty( $attributes['showButton'] )
		);
	}

	/**
	 * Builds email-safe HTML for a list of posts.
	 *
	 * @param array<int,\WP_Post> $posts Posts.
	 * @param bool                $show_image Whether to include featured images.
	 * @param bool                $show_excerpt Whether to include excerpts.
	 * @param bool                $show_button Whether to include the read-more button.
	 * @return string
	 */
	private function render_posts_html( $posts, $show_image, $show_excerpt, $show_button ) {
		$html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;">';

		foreach ( $posts as $post ) {
			$permalink = get_permalink( $post );
			$title     = get_the_title( $post );

			$image_html = '';
			if ( $show_image && has_post_thumbnail( $post ) ) {
				$image_url = get_the_post_thumbnail_url( $post, 'large' );
				if ( $image_url ) {
					$image_html = sprintf(
						'<a href="%1$s" style="display:block;"><img src="%2$s" alt="%3$s" style="display:block;width:100%%;max-width:624px;height:auto;border-radius:6px;margin:0 0 14px 0;"></a>',
						esc_url( $permalink ),
						esc_url( $image_url ),
						esc_attr( $title )
					);
				}
			}

			$excerpt_html = '';
			if ( $show_excerpt ) {
				$excerpt      = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 28 );
				$excerpt_html = '<p style="margin:0 0 12px;color:#50575e;">' . esc_html( $excerpt ) . '</p>';
			}

			$button_html = '';
			if ( $show_button ) {
				$button_html = sprintf(
					'<p style="margin:0;"><a href="%1$s" style="background:#1d2327;border-radius:4px;color:#ffffff;display:inline-block;padding:9px 14px;text-decoration:none;">%2$s</a></p>',
					esc_url( $permalink ),
					esc_html__( 'Read more', 'greenberry' )
				);
			}

			$html .= sprintf(
				'<tr><td style="padding:18px 0;border-top:1px solid #e2e4e7;">%1$s<h2 style="font-size:20px;line-height:1.3;margin:0 0 8px 0;"><a href="%2$s" style="color:#1d2327;text-decoration:none;">%3$s</a></h2>%4$s%5$s</td></tr>',
				$image_html,
				esc_url( $permalink ),
				esc_html( $title ),
				$excerpt_html,
				$button_html
			);
		}

		$html .= '</table>';

		return $html;
	}

	/**
	 * Renders the Newsletter signup form.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_newsletter_form( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'heading'        => __( 'Stay in the loop', 'greenberry' ),
				'description'    => __( 'Get the latest updates by email.', 'greenberry' ),
				'buttonLabel'    => __( 'Subscribe', 'greenberry' ),
				'showName'       => false,
				'tags'           => 'newsletter',
				'consentText'    => __( 'I agree to receive email updates and understand I can unsubscribe at any time.', 'greenberry' ),
				'successMessage' => __( 'Thanks. Please check your inbox for future updates.', 'greenberry' ),
			)
		);

		$form_id = wp_unique_id( 'greenberry-newsletter-form-' );
		$message = '';

		if ( isset( $_GET['greenberry_newsletter'] ) && 'subscribed' === $_GET['greenberry_newsletter'] ) {
			$message = $attributes['successMessage'];
		} elseif ( isset( $_GET['greenberry_newsletter_error'] ) ) {
			$message = sanitize_text_field( wp_unslash( $_GET['greenberry_newsletter_error'] ) );
		}

		ob_start();
		?>
		<div class="greenberry-newsletter-form" data-endpoint="<?php echo esc_url( rest_url( 'greenberry/v1/newsletter/subscribe' ) ); ?>" data-success-message="<?php echo esc_attr( $attributes['successMessage'] ); ?>">
			<?php if ( '' !== $attributes['heading'] ) : ?>
				<h2 class="greenberry-newsletter-form__heading"><?php echo esc_html( $attributes['heading'] ); ?></h2>
			<?php endif; ?>

			<?php if ( '' !== $attributes['description'] ) : ?>
				<p class="greenberry-newsletter-form__description"><?php echo esc_html( $attributes['description'] ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-newsletter-form__form">
				<input type="hidden" name="action" value="greenberry_newsletter_subscribe">
				<input type="hidden" name="tags" value="<?php echo esc_attr( $attributes['tags'] ); ?>">
				<input type="hidden" name="consent_text" value="<?php echo esc_attr( $attributes['consentText'] ); ?>">

				<div class="greenberry-newsletter-form__fields">
					<?php if ( ! empty( $attributes['showName'] ) ) : ?>
						<label class="greenberry-newsletter-form__field" for="<?php echo esc_attr( $form_id ); ?>-name">
							<span><?php esc_html_e( 'Name', 'greenberry' ); ?></span>
							<input id="<?php echo esc_attr( $form_id ); ?>-name" type="text" name="first_name" autocomplete="given-name">
						</label>
					<?php endif; ?>

					<label class="greenberry-newsletter-form__field" for="<?php echo esc_attr( $form_id ); ?>-email">
						<span><?php esc_html_e( 'Email', 'greenberry' ); ?></span>
						<input id="<?php echo esc_attr( $form_id ); ?>-email" type="email" name="email" autocomplete="email" required>
					</label>

					<label class="greenberry-newsletter-form__honeypot" aria-hidden="true">
						<span><?php esc_html_e( 'Website', 'greenberry' ); ?></span>
						<input type="text" name="website" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<label class="greenberry-newsletter-form__consent">
					<input type="checkbox" name="consent" value="1" required>
					<span><?php echo esc_html( $attributes['consentText'] ); ?></span>
				</label>

				<div class="greenberry-newsletter-form__submit-row">
					<button type="submit" class="greenberry-newsletter-form__button"><?php echo esc_html( $attributes['buttonLabel'] ); ?></button>
					<span class="greenberry-newsletter-form__status" role="status" aria-live="polite"><?php echo esc_html( $message ); ?></span>
				</div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
