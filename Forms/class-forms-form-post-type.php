<?php
/**
 * Form post type.
 *
 * Forms are designed in the native block editor using Greenberry field blocks.
 * Delivery settings (recipient, subject, replies, success message) live in a
 * meta box. The form is embedded on pages with the Greenberry Form block in
 * "saved" mode, and submitted through the existing REST/admin-post pipeline.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Form block-editor post type and its delivery settings.
 */
class Form_Post_Type {
	const POST_TYPE = 'greenberry_form';

	const META_DESCRIPTION   = '_greenberry_form_description';
	const META_RECIPIENT     = '_greenberry_form_recipient';
	const META_SUBJECT       = '_greenberry_form_subject';
	const META_REPLY_TO      = '_greenberry_form_reply_to';
	const META_COPY_TO       = '_greenberry_form_copy_to';
	const META_COPY_SUBJECT  = '_greenberry_form_copy_subject';
	const META_COPY_MESSAGE  = '_greenberry_form_copy_message';
	const META_SUBMIT_LABEL  = '_greenberry_form_submit_label';
	const META_SUCCESS       = '_greenberry_form_success';
	const META_TURNSTILE     = '_greenberry_form_turnstile';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
			add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_settings_meta' ), 10, 2 );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Registers the form post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Forms', 'greenberry' ),
					'singular_name'      => __( 'Form', 'greenberry' ),
					'add_new'            => __( 'Add Form', 'greenberry' ),
					'add_new_item'       => __( 'Add Form', 'greenberry' ),
					'edit_item'          => __( 'Edit Form', 'greenberry' ),
					'new_item'           => __( 'New Form', 'greenberry' ),
					'search_items'       => __( 'Search Forms', 'greenberry' ),
					'not_found'          => __( 'No forms yet.', 'greenberry' ),
					'not_found_in_trash' => __( 'No forms in the bin.', 'greenberry' ),
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
				'menu_icon'           => 'dashicons-feedback',
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'template'            => array(
					array( 'greenberry/form-field', array( 'label' => __( 'Text field', 'greenberry' ), 'key' => 'text_field', 'type' => 'text', 'required' => true, 'placeholder' => __( 'Enter text', 'greenberry' ) ) ),
					array( 'greenberry/form-field', array( 'label' => __( 'Paragraph field', 'greenberry' ), 'key' => 'paragraph_field', 'type' => 'paragraph', 'required' => true, 'placeholder' => __( 'Enter details', 'greenberry' ) ) ),
					array( 'greenberry/form-field', array( 'label' => __( 'Checkbox field', 'greenberry' ), 'key' => 'checkbox_field', 'type' => 'checkbox', 'required' => true ) ),
				),
			)
		);
	}

	/**
	 * Registers the delivery settings meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			'greenberry-form-settings',
			__( 'Form settings', 'greenberry' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Renders the settings meta box.
	 *
	 * @param \WP_Post $post Form post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'greenberry_form_settings', 'greenberry_form_settings_nonce' );
		$config = self::config_for( $post );
		?>
		<p>
			<label for="greenberry-form-recipient"><strong><?php esc_html_e( 'Send submissions to', 'greenberry' ); ?></strong></label>
			<input type="email" id="greenberry-form-recipient" name="greenberry_form_recipient" class="widefat" value="<?php echo esc_attr( $config['recipient_email'] ); ?>">
		</p>
		<p>
			<label for="greenberry-form-subject"><strong><?php esc_html_e( 'Email subject', 'greenberry' ); ?></strong></label>
			<input type="text" id="greenberry-form-subject" name="greenberry_form_subject" class="widefat" value="<?php echo esc_attr( $config['subject'] ); ?>">
			<span class="description"><?php esc_html_e( 'Variables: {site_name}, {form_title}, or a field key like {email}.', 'greenberry' ); ?></span>
		</p>
		<p>
			<label for="greenberry-form-reply-to"><strong><?php esc_html_e( 'Reply-To field key', 'greenberry' ); ?></strong></label>
			<input type="text" id="greenberry-form-reply-to" name="greenberry_form_reply_to" class="widefat" value="<?php echo esc_attr( $config['reply_to_field'] ); ?>" placeholder="email">
		</p>
		<p>
			<label for="greenberry-form-copy-to"><strong><?php esc_html_e( 'Send submitter a copy (field key)', 'greenberry' ); ?></strong></label>
			<input type="text" id="greenberry-form-copy-to" name="greenberry_form_copy_to" class="widefat" value="<?php echo esc_attr( $config['copy_to_field'] ); ?>" placeholder="email">
		</p>
		<p>
			<label for="greenberry-form-copy-subject"><strong><?php esc_html_e( 'Submitter copy subject', 'greenberry' ); ?></strong></label>
			<input type="text" id="greenberry-form-copy-subject" name="greenberry_form_copy_subject" class="widefat" value="<?php echo esc_attr( $config['copy_subject'] ); ?>">
		</p>
		<p>
			<label for="greenberry-form-copy-message"><strong><?php esc_html_e( 'Submitter copy message', 'greenberry' ); ?></strong></label>
			<textarea id="greenberry-form-copy-message" name="greenberry_form_copy_message" class="widefat" rows="3"><?php echo esc_textarea( $config['copy_message'] ); ?></textarea>
		</p>
		<p>
			<label for="greenberry-form-submit-label"><strong><?php esc_html_e( 'Submit button label', 'greenberry' ); ?></strong></label>
			<input type="text" id="greenberry-form-submit-label" name="greenberry_form_submit_label" class="widefat" value="<?php echo esc_attr( $config['submit_label'] ); ?>">
		</p>
		<p>
			<label for="greenberry-form-success"><strong><?php esc_html_e( 'Success message', 'greenberry' ); ?></strong></label>
			<input type="text" id="greenberry-form-success" name="greenberry_form_success" class="widefat" value="<?php echo esc_attr( $config['success_message'] ); ?>">
		</p>
		<p>
			<label for="greenberry-form-description"><strong><?php esc_html_e( 'Intro description', 'greenberry' ); ?></strong></label>
			<textarea id="greenberry-form-description" name="greenberry_form_description" class="widefat" rows="2"><?php echo esc_textarea( $config['description'] ); ?></textarea>
		</p>
		<p>
			<label>
				<input type="checkbox" name="greenberry_form_turnstile" value="1" <?php checked( ! empty( $config['turnstile_required'] ) ); ?>>
				<?php esc_html_e( 'Require Cloudflare Turnstile protection', 'greenberry' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Saves the settings meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_settings_meta( $post_id, $post ) {
		if ( ! isset( $_POST['greenberry_form_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['greenberry_form_settings_nonce'] ) ), 'greenberry_form_settings' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text = array(
			self::META_SUBJECT      => 'greenberry_form_subject',
			self::META_REPLY_TO     => 'greenberry_form_reply_to',
			self::META_COPY_TO      => 'greenberry_form_copy_to',
			self::META_COPY_SUBJECT => 'greenberry_form_copy_subject',
			self::META_SUBMIT_LABEL => 'greenberry_form_submit_label',
			self::META_SUCCESS      => 'greenberry_form_success',
		);

		foreach ( $text as $meta_key => $field ) {
			update_post_meta(
				$post_id,
				$meta_key,
				isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''
			);
		}

		update_post_meta(
			$post_id,
			self::META_RECIPIENT,
			isset( $_POST['greenberry_form_recipient'] ) ? sanitize_email( wp_unslash( $_POST['greenberry_form_recipient'] ) ) : ''
		);
		update_post_meta(
			$post_id,
			self::META_COPY_MESSAGE,
			isset( $_POST['greenberry_form_copy_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['greenberry_form_copy_message'] ) ) : ''
		);
		update_post_meta(
			$post_id,
			self::META_DESCRIPTION,
			isset( $_POST['greenberry_form_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['greenberry_form_description'] ) ) : ''
		);
		update_post_meta( $post_id, self::META_TURNSTILE, empty( $_POST['greenberry_form_turnstile'] ) ? 0 : 1 );
	}

	/**
	 * Loads a form definition from a CPT post ID.
	 *
	 * @param int $form_id Form post ID.
	 * @return array|null
	 */
	public static function load_form( $form_id ) {
		$post = get_post( absint( $form_id ) );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}

		return self::config_for( $post );
	}

	/**
	 * Returns the first published form, if any.
	 *
	 * @return array|null
	 */
	public static function first_form() {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'private' ),
				'numberposts'      => 1,
				'orderby'          => 'menu_order title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		return empty( $posts ) ? null : self::config_for( $posts[0] );
	}

	/**
	 * Builds a normalized form definition from a post.
	 *
	 * @param \WP_Post $post Form post.
	 * @return array
	 */
	public static function config_for( $post ) {
		$recipient = sanitize_email( (string) get_post_meta( $post->ID, self::META_RECIPIENT, true ) );
		if ( ! is_email( $recipient ) ) {
			$recipient = sanitize_email( get_option( 'admin_email' ) );
		}

		return array(
			'id'                 => absint( $post->ID ),
			'title'              => get_the_title( $post ),
			'description'        => (string) get_post_meta( $post->ID, self::META_DESCRIPTION, true ),
			'recipient_email'    => $recipient,
			'subject'            => self::meta_or( $post->ID, self::META_SUBJECT, '[{site_name}] {form_title}' ),
			'reply_to_field'     => sanitize_key( (string) get_post_meta( $post->ID, self::META_REPLY_TO, true ) ),
			'copy_to_field'      => sanitize_key( (string) get_post_meta( $post->ID, self::META_COPY_TO, true ) ),
			'copy_subject'       => self::meta_or( $post->ID, self::META_COPY_SUBJECT, __( 'We received your message', 'greenberry' ) ),
			'copy_message'       => self::meta_or( $post->ID, self::META_COPY_MESSAGE, __( 'Thanks for contacting {site_name}. We have received your message and will reply if needed.', 'greenberry' ) ),
			'submit_label'       => self::meta_or( $post->ID, self::META_SUBMIT_LABEL, __( 'Send', 'greenberry' ) ),
			'success_message'    => self::meta_or( $post->ID, self::META_SUCCESS, __( 'Thanks. Your message has been sent.', 'greenberry' ) ),
			'turnstile_required' => (bool) get_post_meta( $post->ID, self::META_TURNSTILE, true ),
			'fields'             => self::fields_from_blocks( parse_blocks( $post->post_content ) ),
		);
	}

	/**
	 * Returns a meta value or a fallback when empty.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private static function meta_or( $post_id, $meta_key, $fallback ) {
		$value = trim( (string) get_post_meta( $post_id, $meta_key, true ) );

		return '' !== $value ? $value : $fallback;
	}

	/**
	 * Extracts sanitized field definitions from parsed blocks (any depth).
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array<int,array>
	 */
	public static function fields_from_blocks( $blocks ) {
		$fields    = array();
		$used_keys = array();

		self::collect_fields( $blocks, $fields, $used_keys );

		return $fields;
	}

	/**
	 * Recursively collects field blocks into a normalized list.
	 *
	 * @param array $blocks    Parsed blocks.
	 * @param array $fields    Accumulated fields (by reference).
	 * @param array $used_keys Used keys map (by reference).
	 * @return void
	 */
	private static function collect_fields( $blocks, &$fields, &$used_keys ) {
		$allowed = array( 'text', 'paragraph', 'date', 'signature', 'checkbox', 'option' );

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( 'greenberry/form-field' !== $block['blockName'] ) {
				if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
					self::collect_fields( $block['innerBlocks'], $fields, $used_keys );
				}
				continue;
			}

			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
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

			$type = isset( $attrs['type'] ) ? self::normalize_field_type( sanitize_key( $attrs['type'] ) ) : 'text';
			if ( ! in_array( $type, $allowed, true ) ) {
				$type = 'text';
			}

			$fields[] = array(
				'key'         => $key,
				'label'       => $label,
				'type'        => $type,
				'required'    => ! empty( $attrs['required'] ),
				'placeholder' => isset( $attrs['placeholder'] ) ? sanitize_text_field( $attrs['placeholder'] ) : '',
				'help_text'   => isset( $attrs['helpText'] ) ? sanitize_text_field( $attrs['helpText'] ) : '',
				'options'     => isset( $attrs['options'] ) ? self::sanitize_options( $attrs['options'] ) : array(),
			);
		}
	}

	/**
	 * Normalizes older field type values to the current simple field set.
	 *
	 * @param string $type Raw field type.
	 * @return string
	 */
	private static function normalize_field_type( $type ) {
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
	private static function sanitize_options( $value ) {
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
				$reordered['greenberry_recipient'] = __( 'Sends to', 'greenberry' );
				$reordered['greenberry_fields']    = __( 'Fields', 'greenberry' );
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
		if ( 'greenberry_recipient' === $column ) {
			echo esc_html( (string) get_post_meta( $post_id, self::META_RECIPIENT, true ) );
			return;
		}

		if ( 'greenberry_fields' === $column ) {
			$config = self::config_for( get_post( $post_id ) );
			echo absint( count( $config['fields'] ) );
		}
	}
}
