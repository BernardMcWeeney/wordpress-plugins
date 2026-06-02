<?php
/**
 * Forms blocks.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Greenberry Form block.
 */
class Blocks {
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
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers dynamic blocks.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$block_dir = GREENBERRY_PLUGIN_DIR . 'Forms/block/form';
		$block_url = GREENBERRY_PLUGIN_URL . 'Forms/block/form/';

		wp_register_script(
			'greenberry-forms-form-editor',
			$block_url . 'editor.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			GREENBERRY_VERSION,
			true
		);

		wp_localize_script(
			'greenberry-forms-form-editor',
			'greenberryFormsBlock',
			array(
				'forms' => $this->get_forms_for_editor(),
			)
		);

		wp_register_script(
			'greenberry-forms-form-view',
			$block_url . 'view.js',
			array(),
			GREENBERRY_VERSION,
			true
		);

		wp_register_style(
			'greenberry-forms-form',
			$block_url . 'style.css',
			array(),
			GREENBERRY_VERSION
		);

		register_block_type(
			$block_dir,
			array(
				'editor_script'   => 'greenberry-forms-form-editor',
				'view_script'     => 'greenberry-forms-form-view',
				'style'           => 'greenberry-forms-form',
				'render_callback' => array( $this, 'render_form_block' ),
			)
		);
	}

	/**
	 * Renders the Form block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_form_block( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'formId'    => 0,
				'showTitle' => true,
			)
		);

		$form = $attributes['formId'] ? $this->repository->get_form( absint( $attributes['formId'] ) ) : $this->repository->get_first_form();
		if ( ! $form ) {
			return current_user_can( 'manage_options' )
				? '<p class="greenberry-form greenberry-form--notice">' . esc_html__( 'Create a Greenberry form before using this block.', 'greenberry' ) . '</p>'
				: '';
		}

		$form_id  = absint( $form['id'] );
		$block_id = wp_unique_id( 'greenberry-form-' . $form_id . '-' );
		$message  = $this->get_query_message( $form );

		ob_start();
		?>
		<div class="greenberry-form" data-endpoint="<?php echo esc_url( rest_url( 'greenberry/v1/forms/submit/' . $form_id ) ); ?>" data-success-message="<?php echo esc_attr( $form['success_message'] ); ?>">
			<?php if ( ! empty( $attributes['showTitle'] ) ) : ?>
				<h2 class="greenberry-form__heading"><?php echo esc_html( $form['title'] ); ?></h2>
			<?php endif; ?>

			<?php if ( '' !== $form['description'] ) : ?>
				<p class="greenberry-form__description"><?php echo esc_html( $form['description'] ); ?></p>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-form__form">
				<input type="hidden" name="action" value="greenberry_forms_submit">
				<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<?php wp_nonce_field( 'greenberry_form_submit_' . $form_id, 'greenberry_form_nonce' ); ?>

				<div class="greenberry-form__fields">
					<?php foreach ( $form['fields'] as $field ) : ?>
						<?php $this->render_field( $field, $block_id ); ?>
					<?php endforeach; ?>

					<label class="greenberry-form__honeypot" aria-hidden="true">
						<span><?php esc_html_e( 'Website', 'greenberry' ); ?></span>
						<input type="text" name="website" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<?php $this->render_turnstile( $form ); ?>

				<div class="greenberry-form__submit-row">
					<button type="submit" class="greenberry-form__button"><?php echo esc_html( $form['submit_label'] ); ?></button>
					<span class="greenberry-form__status" role="status" aria-live="polite"><?php echo esc_html( $message ); ?></span>
				</div>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders one field.
	 *
	 * @param array  $field Field definition.
	 * @param string $block_id Block ID.
	 * @return void
	 */
	private function render_field( $field, $block_id ) {
		$field_id = $block_id . '-' . $field['key'];
		$help_id  = $field_id . '-help';
		$name     = 'file' === $field['type'] ? 'greenberry_files[' . $field['key'] . ']' : 'greenberry_fields[' . $field['key'] . ']';
		$required = ! empty( $field['required'] );
		$described_by = '' !== $field['help_text'] ? $help_id : '';
		?>
		<label class="greenberry-form__field greenberry-form__field--<?php echo esc_attr( $field['type'] ); ?>" for="<?php echo esc_attr( $field_id ); ?>">
			<span class="greenberry-form__label-text">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $required ) : ?>
					<span class="greenberry-form__required" aria-hidden="true">*</span>
				<?php endif; ?>
			</span>

			<?php if ( 'textarea' === $field['type'] || 'address' === $field['type'] ) : ?>
				<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="<?php echo 'address' === $field['type'] ? '3' : '5'; ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?> <?php echo 'address' === $field['type'] ? 'autocomplete="street-address"' : ''; ?>></textarea>
			<?php elseif ( 'checkbox' === $field['type'] ) : ?>
				<span class="greenberry-form__checkbox-row">
					<input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>>
					<span><?php esc_html_e( 'Confirmed', 'greenberry' ); ?></span>
				</span>
			<?php elseif ( 'file' === $field['type'] ) : ?>
				<input id="<?php echo esc_attr( $field_id ); ?>" type="file" name="<?php echo esc_attr( $name ); ?>" accept="<?php echo esc_attr( $field['accept'] ); ?>" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>>
			<?php else : ?>
				<input id="<?php echo esc_attr( $field_id ); ?>" type="<?php echo 'email' === $field['type'] ? 'email' : 'text'; ?>" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" <?php echo 'email' === $field['type'] ? 'autocomplete="email"' : ''; ?> <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>>
			<?php endif; ?>

			<?php if ( '' !== $field['help_text'] ) : ?>
				<span id="<?php echo esc_attr( $help_id ); ?>" class="greenberry-form__help" title="<?php echo esc_attr( $field['help_text'] ); ?>"><?php echo esc_html( $field['help_text'] ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Renders Simple Cloudflare Turnstile when available.
	 *
	 * @param array $form Form definition.
	 * @return void
	 */
	private function render_turnstile( $form ) {
		if ( empty( $form['turnstile_required'] ) ) {
			return;
		}

		echo '<div class="greenberry-form__turnstile">';

		if ( shortcode_exists( 'simple-turnstile' ) ) {
			do_action( 'cfturnstile_enqueue_scripts' );
			echo do_shortcode( '[simple-turnstile]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( function_exists( 'cfturnstile_field_show' ) ) {
			do_action( 'cfturnstile_enqueue_scripts' );
			echo cfturnstile_field_show( '', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( current_user_can( 'manage_options' ) ) {
			echo '<p class="greenberry-form__warning">' . esc_html__( 'Simple Cloudflare Turnstile is required for this form but is not active or configured.', 'greenberry' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Gets current query status for non-JS fallback.
	 *
	 * @param array $form Form definition.
	 * @return string
	 */
	private function get_query_message( $form ) {
		$query_form_id = isset( $_GET['greenberry_form_id'] ) ? absint( $_GET['greenberry_form_id'] ) : 0;
		if ( absint( $form['id'] ) !== $query_form_id ) {
			return '';
		}

		if ( isset( $_GET['greenberry_form_sent'] ) ) {
			return $form['success_message'];
		}

		if ( isset( $_GET['greenberry_form_error'] ) ) {
			return sanitize_text_field( wp_unslash( $_GET['greenberry_form_error'] ) );
		}

		return '';
	}

	/**
	 * Returns saved forms for the editor select.
	 *
	 * @return array<int,array{id:int,title:string}>
	 */
	private function get_forms_for_editor() {
		$forms = array();
		foreach ( $this->repository->get_forms() as $form ) {
			$forms[] = array(
				'id'    => absint( $form['id'] ),
				'title' => $form['title'],
			);
		}

		return $forms;
	}
}
