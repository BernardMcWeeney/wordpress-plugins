<?php
/**
 * Forms admin screens.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles Forms admin workflows.
 */
class Admin {
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
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_greenberry_forms_save', array( $this, 'save_form' ) );
		add_action( 'admin_post_greenberry_forms_delete', array( $this, 'delete_form' ) );
	}

	/**
	 * Registers Forms submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'greenberry',
			__( 'Forms', 'greenberry' ),
			__( 'Forms', 'greenberry' ),
			'manage_options',
			'greenberry-forms',
			array( $this, 'render' )
		);
	}

	/**
	 * Loads admin helpers.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'greenberry-forms' ) ) {
			return;
		}

		wp_enqueue_script(
			'greenberry-forms-admin',
			GREENBERRY_PLUGIN_URL . 'Forms/admin.js',
			array(),
			GREENBERRY_VERSION,
			true
		);
	}

	/**
	 * Saves a form definition.
	 *
	 * @return void
	 */
	public function save_form() {
		$this->guard_action( 'greenberry_forms_save' );

		$result = $this->repository->save_form( $_POST );
		$args   = array(
			'page' => 'greenberry-forms',
		);

		if ( is_wp_error( $result ) ) {
			$args['greenberry_notice'] = $result->get_error_code();
			$args['form_id']           = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		} else {
			$args['greenberry_notice'] = 'form_saved';
			$args['form_id']           = absint( $result );
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Deletes a form definition.
	 *
	 * @return void
	 */
	public function delete_form() {
		$this->guard_action( 'greenberry_forms_delete' );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$this->repository->delete_form( $form_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'greenberry-forms',
					'greenberry_notice' => 'form_deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renders the Forms admin page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$forms   = $this->repository->get_forms();
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$form    = $form_id ? $this->repository->get_form( $form_id ) : null;

		if ( ! $form ) {
			$form = $this->new_form_template();
		}

		?>
		<div class="wrap greenberry-admin">
			<h1><?php esc_html_e( 'Forms', 'greenberry' ); ?></h1>
			<?php $this->render_notice(); ?>

			<div class="greenberry-grid greenberry-grid--forms">
				<div class="greenberry-panel">
					<h2><?php esc_html_e( 'Saved Forms', 'greenberry' ); ?></h2>
					<p>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'greenberry-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'New Form', 'greenberry' ); ?>
						</a>
					</p>

					<?php if ( empty( $forms ) ) : ?>
						<p class="greenberry-muted"><?php esc_html_e( 'No forms have been created yet.', 'greenberry' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Form', 'greenberry' ); ?></th>
									<th><?php esc_html_e( 'Fields', 'greenberry' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $forms as $saved_form ) : ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'greenberry-forms', 'form_id' => absint( $saved_form['id'] ) ), admin_url( 'admin.php' ) ) ); ?>">
												<strong><?php echo esc_html( $saved_form['title'] ); ?></strong>
											</a>
											<div class="greenberry-muted"><?php echo esc_html( $saved_form['recipient_email'] ); ?></div>
										</td>
										<td><?php echo absint( count( $saved_form['fields'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<div class="greenberry-panel">
					<h2><?php echo $form['id'] ? esc_html__( 'Edit Form', 'greenberry' ) : esc_html__( 'Create Form', 'greenberry' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="greenberry_forms_save">
						<input type="hidden" name="id" value="<?php echo esc_attr( $form['id'] ); ?>">
						<?php wp_nonce_field( 'greenberry_forms_save' ); ?>

						<?php $this->render_settings_fields( $form ); ?>
						<?php $this->render_form_fields_table( $form ); ?>

						<?php submit_button( __( 'Save Form', 'greenberry' ) ); ?>
					</form>

					<?php if ( $form['id'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-danger-zone">
							<input type="hidden" name="action" value="greenberry_forms_delete">
							<input type="hidden" name="form_id" value="<?php echo esc_attr( $form['id'] ); ?>">
							<?php wp_nonce_field( 'greenberry_forms_delete' ); ?>
							<?php submit_button( __( 'Delete Form', 'greenberry' ), 'delete', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders form settings.
	 *
	 * @param array $form Form definition.
	 * @return void
	 */
	private function render_settings_fields( $form ) {
		$email_fields = $this->repository->get_email_fields( $form );
		?>
		<div class="greenberry-section">
			<h3><?php esc_html_e( 'Settings', 'greenberry' ); ?></h3>
			<div class="greenberry-field">
				<label for="greenberry-form-title"><?php esc_html_e( 'Form title', 'greenberry' ); ?></label>
				<input id="greenberry-form-title" type="text" name="title" value="<?php echo esc_attr( $form['title'] ); ?>" required>
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-description"><?php esc_html_e( 'Description', 'greenberry' ); ?></label>
				<textarea id="greenberry-form-description" name="description" rows="2"><?php echo esc_textarea( $form['description'] ); ?></textarea>
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-recipient"><?php esc_html_e( 'Send submissions to', 'greenberry' ); ?></label>
				<input id="greenberry-form-recipient" type="email" name="recipient_email" value="<?php echo esc_attr( $form['recipient_email'] ); ?>" required>
				<p class="description"><?php esc_html_e( 'Submissions are emailed to this address and are not stored by Greenberry.', 'greenberry' ); ?></p>
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-subject"><?php esc_html_e( 'Email subject', 'greenberry' ); ?></label>
				<input id="greenberry-form-subject" type="text" name="subject" value="<?php echo esc_attr( $form['subject'] ); ?>">
				<p class="description"><?php esc_html_e( 'Variables: {site_name}, {site_url}, {form_title}, or a field key like {email}.', 'greenberry' ); ?></p>
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-reply-to"><?php esc_html_e( 'Reply-To email field', 'greenberry' ); ?></label>
				<?php $this->render_email_field_select( 'reply_to_field', 'greenberry-form-reply-to', $form['reply_to_field'], $email_fields ); ?>
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-copy-to"><?php esc_html_e( 'Send submitter copy to', 'greenberry' ); ?></label>
				<?php $this->render_email_field_select( 'copy_to_field', 'greenberry-form-copy-to', $form['copy_to_field'], $email_fields ); ?>
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-copy-subject"><?php esc_html_e( 'Submitter copy subject', 'greenberry' ); ?></label>
				<input id="greenberry-form-copy-subject" type="text" name="copy_subject" value="<?php echo esc_attr( $form['copy_subject'] ); ?>">
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-copy-message"><?php esc_html_e( 'Submitter copy message', 'greenberry' ); ?></label>
				<textarea id="greenberry-form-copy-message" name="copy_message" rows="3"><?php echo esc_textarea( $form['copy_message'] ); ?></textarea>
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-submit-label"><?php esc_html_e( 'Submit button label', 'greenberry' ); ?></label>
				<input id="greenberry-form-submit-label" type="text" name="submit_label" value="<?php echo esc_attr( $form['submit_label'] ); ?>">
			</div>
			<div class="greenberry-field">
				<label for="greenberry-form-success"><?php esc_html_e( 'Success message', 'greenberry' ); ?></label>
				<input id="greenberry-form-success" type="text" name="success_message" value="<?php echo esc_attr( $form['success_message'] ); ?>">
			</div>
			<label class="greenberry-field greenberry-checkbox-field">
				<input type="checkbox" name="turnstile_required" value="1" <?php checked( ! empty( $form['turnstile_required'] ) ); ?>>
				<?php esc_html_e( 'Require Simple Cloudflare Turnstile protection', 'greenberry' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Renders the field builder table.
	 *
	 * @param array $form Form definition.
	 * @return void
	 */
	private function render_form_fields_table( $form ) {
		$fields = $form['fields'];
		if ( empty( $fields ) ) {
			$fields = array(
				array(
					'key'           => '',
					'label'         => '',
					'type'          => 'text',
					'required'      => false,
					'placeholder'   => '',
					'help_text'     => '',
					'accept'        => '',
					'max_file_size' => 5,
				),
			);
		}
		?>
		<div class="greenberry-section">
			<h3><?php esc_html_e( 'Fields', 'greenberry' ); ?></h3>
			<table class="widefat striped greenberry-form-fields">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Label', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'Key', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'Type', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'Required', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'Help / Placeholder', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'File Rules', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'greenberry' ); ?></th>
					</tr>
				</thead>
				<tbody data-greenberry-fields>
					<?php foreach ( $fields as $index => $field ) : ?>
						<?php $this->render_field_row( $field, $index ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" data-greenberry-add-field><?php esc_html_e( 'Add Field', 'greenberry' ); ?></button>
			</p>
			<script type="text/html" id="greenberry-field-template">
				<?php $this->render_field_row( $this->blank_field(), '__INDEX__' ); ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Renders one field builder row.
	 *
	 * @param array      $field Field definition.
	 * @param int|string $index Field index.
	 * @return void
	 */
	private function render_field_row( $field, $index ) {
		$field = wp_parse_args( $field, $this->blank_field() );
		?>
		<tr class="greenberry-form-field-row">
			<td>
				<input type="text" name="fields[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ); ?>" placeholder="<?php esc_attr_e( 'Field label', 'greenberry' ); ?>">
			</td>
			<td>
				<input type="text" name="fields[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $field['key'] ); ?>" placeholder="<?php esc_attr_e( 'email', 'greenberry' ); ?>">
			</td>
			<td>
				<select name="fields[<?php echo esc_attr( $index ); ?>][type]">
					<?php foreach ( $this->field_type_options() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<label>
					<input type="checkbox" name="fields[<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>>
					<?php esc_html_e( 'Yes', 'greenberry' ); ?>
				</label>
			</td>
			<td>
				<input type="text" name="fields[<?php echo esc_attr( $index ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ); ?>" placeholder="<?php esc_attr_e( 'Placeholder', 'greenberry' ); ?>">
				<input type="text" name="fields[<?php echo esc_attr( $index ); ?>][help_text]" value="<?php echo esc_attr( $field['help_text'] ); ?>" placeholder="<?php esc_attr_e( 'Help text or tooltip', 'greenberry' ); ?>">
			</td>
			<td>
				<input type="text" name="fields[<?php echo esc_attr( $index ); ?>][accept]" value="<?php echo esc_attr( $field['accept'] ); ?>" placeholder=".pdf,.jpg">
				<input type="number" min="1" max="25" name="fields[<?php echo esc_attr( $index ); ?>][max_file_size]" value="<?php echo esc_attr( $field['max_file_size'] ); ?>">
				<span class="greenberry-muted"><?php esc_html_e( 'MB', 'greenberry' ); ?></span>
			</td>
			<td>
				<button type="button" class="button-link-delete" data-greenberry-remove-field><?php esc_html_e( 'Remove', 'greenberry' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders an email field select.
	 *
	 * @param string $name Input name.
	 * @param string $id Input ID.
	 * @param string $selected Selected value.
	 * @param array  $email_fields Email field options.
	 * @return void
	 */
	private function render_email_field_select( $name, $id, $selected, $email_fields ) {
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
			<option value=""><?php esc_html_e( 'None', 'greenberry' ); ?></option>
			<?php foreach ( $email_fields as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>><?php echo esc_html( $label . ' {' . $key . '}' ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Renders admin notice.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['greenberry_notice'] ) ) {
			return;
		}

		$notice   = sanitize_key( $_GET['greenberry_notice'] );
		$messages = array(
			'form_saved'        => __( 'Form saved.', 'greenberry' ),
			'form_deleted'      => __( 'Form deleted.', 'greenberry' ),
			'missing_title'     => __( 'Please enter a form title.', 'greenberry' ),
			'missing_fields'    => __( 'Please add at least one form field.', 'greenberry' ),
			'invalid_recipient' => __( 'Please enter a valid recipient email address.', 'greenberry' ),
		);

		if ( empty( $messages[ $notice ] ) ) {
			return;
		}

		$type = in_array( $notice, array( 'form_saved', 'form_deleted' ), true ) ? 'success' : 'error';
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p><?php echo esc_html( $messages[ $notice ] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Guards an admin post action.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function guard_action( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Greenberry forms.', 'greenberry' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Returns a blank new form.
	 *
	 * @return array
	 */
	private function new_form_template() {
		return array(
			'id'                 => 0,
			'title'              => '',
			'description'        => '',
			'recipient_email'    => sanitize_email( get_option( 'admin_email' ) ),
			'subject'            => '[{site_name}] {form_title}',
			'reply_to_field'     => '',
			'copy_to_field'      => '',
			'copy_subject'       => __( 'We received your message', 'greenberry' ),
			'copy_message'       => __( 'Thanks for contacting {site_name}. We have received your message and will reply if needed.', 'greenberry' ),
			'submit_label'       => __( 'Send', 'greenberry' ),
			'success_message'    => __( 'Thanks. Your message has been sent.', 'greenberry' ),
			'turnstile_required' => true,
			'fields'             => array(
				$this->blank_field(),
			),
		);
	}

	/**
	 * Returns a blank field definition.
	 *
	 * @return array
	 */
	private function blank_field() {
		return array(
			'key'           => '',
			'label'         => '',
			'type'          => 'text',
			'required'      => false,
			'placeholder'   => '',
			'help_text'     => '',
			'accept'        => '',
			'max_file_size' => 5,
		);
	}

	/**
	 * Returns field type options.
	 *
	 * @return array<string,string>
	 */
	private function field_type_options() {
		return array(
			'text'     => __( 'Text', 'greenberry' ),
			'email'    => __( 'Email', 'greenberry' ),
			'textarea' => __( 'Long Text', 'greenberry' ),
			'address'  => __( 'Address', 'greenberry' ),
			'checkbox' => __( 'Checkbox', 'greenberry' ),
			'file'     => __( 'File Upload', 'greenberry' ),
		);
	}
}
