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
	 * @param string $content Saved nested block content.
	 * @param \WP_Block|null $block Parsed block instance.
	 * @return string
	 */
	public function render_form_block( $attributes, $content = '', $block = null ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'mode'              => 'saved',
				'formId'            => 0,
				'showTitle'         => true,
				'title'             => __( 'Contact form', 'greenberry' ),
				'description'       => __( 'Send a message to the site owner.', 'greenberry' ),
				'recipientEmail'    => sanitize_email( get_option( 'admin_email' ) ),
				'subject'           => '[{site_name}] {form_title}',
				'replyToField'      => 'email',
				'copyToField'       => 'email',
				'copySubject'       => __( 'We received your message', 'greenberry' ),
				'copyMessage'       => __( 'Thanks for contacting {site_name}. We have received your message and will reply if needed.', 'greenberry' ),
				'submitLabel'       => __( 'Send message', 'greenberry' ),
				'successMessage'    => __( 'Thanks. Your message has been sent.', 'greenberry' ),
				'turnstileRequired' => true,
			)
		);

		$visual_form = $this->get_visual_form_from_block( $attributes, $block );
		$is_visual   = 'visual' === $attributes['mode'] && $visual_form;

		if ( $is_visual ) {
			$form = $visual_form;
		} elseif ( $attributes['formId'] ) {
			$form = Form_Post_Type::load_form( absint( $attributes['formId'] ) );
		} else {
			$form = Form_Post_Type::first_form();
		}

		if ( ! $form ) {
			return current_user_can( 'manage_options' )
				? '<p class="greenberry-form greenberry-form--notice">' . esc_html__( 'Create a Greenberry form before using this block.', 'greenberry' ) . '</p>'
				: '';
		}

		$form_id     = $is_visual ? 0 : absint( $form['id'] );
		$form_key    = $is_visual ? $this->store_visual_form( $form ) : '';
		$block_id    = wp_unique_id( 'greenberry-form-' . ( $is_visual ? $form_key : $form_id ) . '-' );
		$message     = $this->get_query_message( $form, $is_visual ? $form_key : (string) $form_id, $is_visual );
		$endpoint    = $is_visual ? rest_url( 'greenberry/v1/forms/submit-block/' . $form_key ) : rest_url( 'greenberry/v1/forms/submit/' . $form_id );
		$nonce_name  = 'greenberry_form_nonce';
		$nonce_action = $is_visual ? 'greenberry_form_submit_block_' . $form_key : 'greenberry_form_submit_' . $form_id;

		ob_start();
		?>
		<div class="greenberry-form" data-endpoint="<?php echo esc_url( $endpoint ); ?>" data-success-message="<?php echo esc_attr( $form['success_message'] ); ?>">
			<?php if ( ! empty( $attributes['showTitle'] ) ) : ?>
				<h2 class="greenberry-form__heading"><?php echo esc_html( $form['title'] ); ?></h2>
			<?php endif; ?>

			<?php if ( '' !== $form['description'] ) : ?>
				<p class="greenberry-form__description"><?php echo esc_html( $form['description'] ); ?></p>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-form__form">
				<input type="hidden" name="action" value="greenberry_forms_submit">
				<?php if ( $is_visual ) : ?>
					<input type="hidden" name="form_key" value="<?php echo esc_attr( $form_key ); ?>">
				<?php else : ?>
					<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
				<?php endif; ?>
				<?php wp_nonce_field( $nonce_action, $nonce_name ); ?>

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
	 * Builds a temporary form definition from nested Gutenberg field blocks.
	 *
	 * @param array          $attributes Parent block attributes.
	 * @param \WP_Block|null $block Parsed block instance.
	 * @return array|null
	 */
	private function get_visual_form_from_block( $attributes, $block ) {
		if ( ! $block || empty( $block->parsed_block['innerBlocks'] ) || ! is_array( $block->parsed_block['innerBlocks'] ) ) {
			return null;
		}

		$fields = $this->get_visual_fields( $block->parsed_block['innerBlocks'] );
		if ( empty( $fields ) ) {
			return null;
		}

		$recipient = isset( $attributes['recipientEmail'] ) ? sanitize_email( $attributes['recipientEmail'] ) : '';
		if ( ! is_email( $recipient ) ) {
			$recipient = sanitize_email( get_option( 'admin_email' ) );
		}

		return array(
			'id'                 => 0,
			'title'              => isset( $attributes['title'] ) && '' !== $attributes['title'] ? sanitize_text_field( $attributes['title'] ) : __( 'Contact form', 'greenberry' ),
			'description'        => isset( $attributes['description'] ) ? sanitize_textarea_field( $attributes['description'] ) : '',
			'recipient_email'    => $recipient,
			'subject'            => isset( $attributes['subject'] ) && '' !== $attributes['subject'] ? sanitize_text_field( $attributes['subject'] ) : '[{site_name}] {form_title}',
			'reply_to_field'     => isset( $attributes['replyToField'] ) ? sanitize_key( $attributes['replyToField'] ) : '',
			'copy_to_field'      => isset( $attributes['copyToField'] ) ? sanitize_key( $attributes['copyToField'] ) : '',
			'copy_subject'       => isset( $attributes['copySubject'] ) && '' !== $attributes['copySubject'] ? sanitize_text_field( $attributes['copySubject'] ) : __( 'We received your message', 'greenberry' ),
			'copy_message'       => isset( $attributes['copyMessage'] ) ? sanitize_textarea_field( $attributes['copyMessage'] ) : __( 'Thanks for contacting {site_name}. We have received your message and will reply if needed.', 'greenberry' ),
			'submit_label'       => isset( $attributes['submitLabel'] ) && '' !== $attributes['submitLabel'] ? sanitize_text_field( $attributes['submitLabel'] ) : __( 'Send', 'greenberry' ),
			'success_message'    => isset( $attributes['successMessage'] ) && '' !== $attributes['successMessage'] ? sanitize_text_field( $attributes['successMessage'] ) : __( 'Thanks. Your message has been sent.', 'greenberry' ),
			'turnstile_required' => ! empty( $attributes['turnstileRequired'] ),
			'fields'             => $fields,
		);
	}

	/**
	 * Extracts sanitized field definitions from nested blocks.
	 *
	 * @param array $inner_blocks Nested parsed blocks.
	 * @return array<int,array>
	 */
	private function get_visual_fields( $inner_blocks ) {
		$fields     = array();
		$used_keys  = array();
		$type_allow = array( 'text', 'paragraph', 'date', 'signature', 'checkbox', 'option' );

		foreach ( $inner_blocks as $inner_block ) {
			if ( empty( $inner_block['blockName'] ) || 'greenberry/form-field' !== $inner_block['blockName'] ) {
				continue;
			}

			$attrs = isset( $inner_block['attrs'] ) && is_array( $inner_block['attrs'] ) ? $inner_block['attrs'] : array();
			$label = isset( $attrs['label'] ) ? sanitize_text_field( $attrs['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$key = isset( $attrs['key'] ) ? sanitize_key( $attrs['key'] ) : '';
			if ( '' === $key ) {
				$key = sanitize_key( sanitize_title( $label ) );
			}
			if ( '' === $key ) {
				$key = 'field';
			}

			$base_key = $key;
			$suffix   = 2;
			while ( isset( $used_keys[ $key ] ) ) {
				$key = $base_key . '_' . $suffix;
				++$suffix;
			}
			$used_keys[ $key ] = true;

			$type = isset( $attrs['type'] ) ? $this->normalize_field_type( sanitize_key( $attrs['type'] ) ) : 'text';
			if ( ! in_array( $type, $type_allow, true ) ) {
				$type = 'text';
			}

			$fields[] = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => $type,
				'required'    => ! empty( $attrs['required'] ),
				'placeholder' => isset( $attrs['placeholder'] ) ? sanitize_text_field( $attrs['placeholder'] ) : '',
				'help_text'   => isset( $attrs['helpText'] ) ? sanitize_text_field( $attrs['helpText'] ) : '',
				'options'     => isset( $attrs['options'] ) ? $this->sanitize_options( $attrs['options'] ) : array(),
			);
		}

		return $fields;
	}

	/**
	 * Normalizes older field type values to the current simple field set.
	 *
	 * @param string $type Raw field type.
	 * @return string
	 */
	private function normalize_field_type( $type ) {
		if ( 'textarea' === $type || 'address' === $type ) {
			return 'paragraph';
		}

		if ( 'email' === $type || 'file' === $type ) {
			return 'text';
		}

		return $type;
	}

	/**
	 * Sanitizes option field choices.
	 *
	 * @param mixed $value Raw options, either an array or newline-separated text.
	 * @return array<int,string>
	 */
	private function sanitize_options( $value ) {
		$options = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		$options = array_map( 'sanitize_text_field', array_map( 'trim', (array) $options ) );
		$options = array_filter(
			$options,
			function ( $option ) {
				return '' !== $option;
			}
		);

		return array_values( array_unique( $options ) );
	}

	/**
	 * Stores a visual block form in a short-lived server-side cache for submission.
	 *
	 * @param array $form Form definition.
	 * @return string Form key.
	 */
	private function store_visual_form( $form ) {
		$form_key = hash( 'sha256', wp_json_encode( $form ) );
		set_transient( Rest::block_form_transient_key( $form_key ), $form, DAY_IN_SECONDS );

		return $form_key;
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
		$name     = 'greenberry_fields[' . $field['key'] . ']';
		$required = ! empty( $field['required'] );
		$described_by = '' !== $field['help_text'] ? $help_id : '';
		?>
		<?php if ( 'checkbox' === $field['type'] ) : ?>
			<label class="greenberry-form__field greenberry-form__field--checkbox" for="<?php echo esc_attr( $field_id ); ?>">
				<span class="greenberry-form__checkbox-row">
					<input id="<?php echo esc_attr( $field_id ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>>
					<span class="greenberry-form__label-text">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( $required ) : ?>
							<span class="greenberry-form__required" aria-hidden="true">*</span>
						<?php endif; ?>
					</span>
				</span>

				<?php if ( '' !== $field['help_text'] ) : ?>
					<span id="<?php echo esc_attr( $help_id ); ?>" class="greenberry-form__help" title="<?php echo esc_attr( $field['help_text'] ); ?>"><?php echo esc_html( $field['help_text'] ); ?></span>
				<?php endif; ?>
			</label>
			<?php return; ?>
		<?php endif; ?>

		<label class="greenberry-form__field greenberry-form__field--<?php echo esc_attr( $field['type'] ); ?>" for="<?php echo esc_attr( $field_id ); ?>">
			<span class="greenberry-form__label-text">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $required ) : ?>
					<span class="greenberry-form__required" aria-hidden="true">*</span>
				<?php endif; ?>
			</span>

			<?php if ( 'paragraph' === $field['type'] ) : ?>
				<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="5" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>></textarea>
			<?php elseif ( 'date' === $field['type'] ) : ?>
				<input id="<?php echo esc_attr( $field_id ); ?>" type="date" name="<?php echo esc_attr( $name ); ?>" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>>
			<?php elseif ( 'signature' === $field['type'] ) : ?>
				<input id="<?php echo esc_attr( $field_id ); ?>" type="text" name="<?php echo esc_attr( $name ); ?>" class="greenberry-form__signature-input" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" autocomplete="name" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>>
			<?php elseif ( 'option' === $field['type'] ) : ?>
				<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>>
					<option value=""><?php esc_html_e( 'Select an option', 'greenberry' ); ?></option>
					<?php foreach ( $this->field_options( $field ) as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input id="<?php echo esc_attr( $field_id ); ?>" type="text" name="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" <?php echo $described_by ? 'aria-describedby="' . esc_attr( $described_by ) . '"' : ''; ?> <?php required( $required ); ?>>
			<?php endif; ?>

			<?php if ( '' !== $field['help_text'] ) : ?>
				<span id="<?php echo esc_attr( $help_id ); ?>" class="greenberry-form__help" title="<?php echo esc_attr( $field['help_text'] ); ?>"><?php echo esc_html( $field['help_text'] ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Returns choices for an option field.
	 *
	 * @param array $field Field definition.
	 * @return array<int,string>
	 */
	private function field_options( $field ) {
		if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			return $field['options'];
		}

		return array();
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

		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST && current_user_can( 'edit_posts' ) ) ) {
			return;
		}

		echo '<div class="greenberry-form__turnstile">';

		try {
			if ( shortcode_exists( 'simple-turnstile' ) ) {
				do_action( 'cfturnstile_enqueue_scripts' );
				echo do_shortcode( '[simple-turnstile]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( function_exists( 'cfturnstile_field_show' ) ) {
				do_action( 'cfturnstile_enqueue_scripts' );
				echo cfturnstile_field_show( '', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( current_user_can( 'manage_options' ) ) {
				echo '<p class="greenberry-form__warning">' . esc_html__( 'Simple Cloudflare Turnstile is required for this form but is not active or configured.', 'greenberry' ) . '</p>';
			}
		} catch ( \Throwable $error ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<p class="greenberry-form__warning">' . esc_html__( 'Simple Cloudflare Turnstile could not be rendered.', 'greenberry' ) . '</p>';
			}
		}

		echo '</div>';
	}

	/**
	 * Gets current query status for non-JS fallback.
	 *
	 * @param array $form Form definition.
	 * @return string
	 */
	private function get_query_message( $form, $identifier, $is_visual = false ) {
		if ( $is_visual ) {
			$query_form_key = isset( $_GET['greenberry_form_key'] ) ? sanitize_key( wp_unslash( $_GET['greenberry_form_key'] ) ) : '';
			if ( $identifier !== $query_form_key ) {
				return '';
			}
		} else {
			$query_form_id = isset( $_GET['greenberry_form_id'] ) ? absint( $_GET['greenberry_form_id'] ) : 0;
			if ( absint( $form['id'] ) !== $query_form_id ) {
				return '';
			}
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
		$posts = get_posts(
			array(
				'post_type'        => Form_Post_Type::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts'      => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$forms = array();
		foreach ( $posts as $post ) {
			$config = Form_Post_Type::config_for( $post );
			$forms[] = array(
				'id'          => $config['id'],
				'title'       => '' !== $config['title'] ? $config['title'] : __( '(untitled form)', 'greenberry' ),
				'description' => $config['description'],
				'submitLabel' => $config['submit_label'],
				'fields'      => $config['fields'],
			);
		}

		return $forms;
	}
}
