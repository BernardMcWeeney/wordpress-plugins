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
