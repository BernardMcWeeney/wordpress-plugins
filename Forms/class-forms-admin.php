<?php
/**
 * Forms admin screen.
 *
 * Lists block-editor form posts. Forms are designed in the block editor; this
 * screen is only the index with links to add and edit.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Forms index screen.
 */
class Admin {
	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_post_greenberry_forms_delete_form', array( $this, 'delete_form' ) );
		add_action( 'admin_init', array( $this, 'redirect_hidden_post_type_list' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_return' ) );
		add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
	}

	/**
	 * Registers the Forms submenu.
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
	 * Renders the Forms index.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$forms = get_posts(
			array(
				'post_type'        => Form_Post_Type::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts'      => 100,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);
		$new_url = $this->editor_url();

		\Greenberry\Admin_UI::open(
			__( 'Forms', 'greenberry' ),
			__( 'Build GDPR-aware forms in the block editor. Submissions are emailed and never stored.', 'greenberry' )
		);
		$this->render_notice();
		?>
		<div class="greenberry-panel">
			<div class="greenberry-section-heading">
				<h2><?php esc_html_e( 'Your forms', 'greenberry' ); ?></h2>
				<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add Form', 'greenberry' ); ?></a>
			</div>
			<p class="greenberry-muted"><?php esc_html_e( 'Design fields with the Greenberry field blocks, set delivery options in the Form settings panel, then add the form to any page with the Greenberry Form block.', 'greenberry' ); ?></p>

			<?php if ( empty( $forms ) ) : ?>
				<p><?php esc_html_e( 'No forms yet. Add your first form to start collecting messages.', 'greenberry' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Form', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Sends to', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Fields', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'greenberry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $forms as $form ) : ?>
							<?php
							$config   = Form_Post_Type::config_for( $form );
							$edit_url = $this->editor_url( $form->ID );
							?>
							<tr>
								<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $form ) ); ?></a></strong></td>
								<td><?php echo esc_html( $config['recipient_email'] ); ?></td>
								<td><?php echo absint( count( $config['fields'] ) ); ?></td>
								<td>
									<div class="greenberry-actions">
										<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'greenberry' ); ?></a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form" onsubmit="return confirm( '<?php echo esc_js( __( 'Delete this form?', 'greenberry' ) ); ?>' );">
											<input type="hidden" name="action" value="greenberry_forms_delete_form">
											<input type="hidden" name="post_id" value="<?php echo esc_attr( $form->ID ); ?>">
											<?php wp_nonce_field( 'greenberry_forms_delete_form' ); ?>
											<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'greenberry' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		\Greenberry\Admin_UI::close();
	}

	/**
	 * Moves a form to the bin.
	 *
	 * @return void
	 */
	public function delete_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Greenberry Forms.', 'greenberry' ) );
		}

		check_admin_referer( 'greenberry_forms_delete_form' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post || Form_Post_Type::POST_TYPE !== $post->post_type ) {
			$this->redirect( 'post_not_found' );
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this form.', 'greenberry' ) );
		}

		$deleted = wp_trash_post( $post_id );

		$this->redirect( $deleted ? 'form_deleted' : 'form_delete_failed' );
	}

	/**
	 * Redirects the hidden native form CPT list to the Greenberry screen.
	 *
	 * @return void
	 */
	public function redirect_hidden_post_type_list() {
		global $pagenow;

		if ( 'edit.php' !== $pagenow || ! empty( $_GET['greenberry_allow_native'] ) ) {
			return;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
		if ( Form_Post_Type::POST_TYPE === $post_type ) {
			wp_safe_redirect( $this->page_url() );
			exit;
		}
	}

	/**
	 * Loads the shared return script on the form editor.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_editor_return( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Form_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$return_url = isset( $_GET['greenberry_return'] )
			? esc_url_raw( wp_unslash( $_GET['greenberry_return'] ) )
			: $this->page_url();

		wp_enqueue_script(
			'greenberry-admin',
			GREENBERRY_PLUGIN_URL . 'assets/admin.js',
			array(),
			GREENBERRY_VERSION,
			true
		);
		wp_add_inline_script(
			'greenberry-admin',
			'window.greenberryEditorReturnUrl = ' . wp_json_encode( $return_url ) . ';',
			'before'
		);
	}

	/**
	 * Highlights the Greenberry parent menu on form editor screens.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string
	 */
	public function highlight_parent_menu( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && Form_Post_Type::POST_TYPE === $screen->post_type ) {
			return 'greenberry';
		}

		return $parent_file;
	}

	/**
	 * Highlights the Forms submenu on form editor screens.
	 *
	 * @param string $submenu_file Current submenu file.
	 * @return string
	 */
	public function highlight_submenu( $submenu_file ) {
		$screen = get_current_screen();
		if ( $screen && Form_Post_Type::POST_TYPE === $screen->post_type ) {
			return 'greenberry-forms';
		}

		return $submenu_file;
	}

	/**
	 * Renders admin notices.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['greenberry_notice'] ) ) {
			return;
		}

		$notice  = sanitize_key( $_GET['greenberry_notice'] );
		$message = array(
			'form_deleted'        => __( 'Form deleted.', 'greenberry' ),
			'form_delete_failed'  => __( 'Could not delete the form.', 'greenberry' ),
			'post_not_found'      => __( 'That form could not be found.', 'greenberry' ),
		);
		$is_error = false !== strpos( $notice, 'failed' ) || false !== strpos( $notice, 'not_found' );
		?>
		<div class="notice notice-<?php echo esc_attr( $is_error ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( isset( $message[ $notice ] ) ? $message[ $notice ] : __( 'Action complete.', 'greenberry' ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Returns the Forms page URL.
	 *
	 * @param array $extra Extra query args.
	 * @return string
	 */
	private function page_url( $extra = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => 'greenberry-forms' ), $extra ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Builds a form add/edit URL that returns to Greenberry Forms.
	 *
	 * @param int $post_id Optional post ID.
	 * @return string
	 */
	private function editor_url( $post_id = 0 ) {
		$post_id = absint( $post_id );
		$url     = $post_id ? get_edit_post_link( $post_id, 'raw' ) : admin_url( 'post-new.php?post_type=' . Form_Post_Type::POST_TYPE );

		if ( ! $url ) {
			$url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		}

		return add_query_arg( 'greenberry_return', $this->page_url(), $url );
	}

	/**
	 * Redirects back to the Forms screen with a notice.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect( $notice ) {
		wp_safe_redirect(
			$this->page_url(
				array(
					'greenberry_notice' => sanitize_key( $notice ),
				)
			)
		);
		exit;
	}
}
