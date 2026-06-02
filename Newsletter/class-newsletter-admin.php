<?php
/**
 * Newsletter admin screens.
 *
 * @package Greenberry
 */

namespace Greenberry\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles Newsletter admin workflows.
 */
class Admin {
	/**
	 * Repository.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Mailer.
	 *
	 * @var Mailer
	 */
	private $mailer;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Repository.
	 * @param Mailer     $mailer Mailer.
	 */
	public function __construct( Repository $repository, Mailer $mailer ) {
		$this->repository = $repository;
		$this->mailer     = $mailer;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_post_greenberry_newsletter_add_contact', array( $this, 'add_contact' ) );
		add_action( 'admin_post_greenberry_newsletter_update_contact', array( $this, 'update_contact' ) );
		add_action( 'admin_post_greenberry_newsletter_delete_contact', array( $this, 'delete_contact' ) );
		add_action( 'admin_post_greenberry_newsletter_import', array( $this, 'import_contacts' ) );
		add_action( 'admin_post_greenberry_newsletter_export', array( $this, 'export_contacts' ) );
		add_action( 'admin_post_greenberry_newsletter_create_list', array( $this, 'create_list' ) );
		add_action( 'admin_post_greenberry_newsletter_update_list', array( $this, 'update_list' ) );
		add_action( 'admin_post_greenberry_newsletter_delete_list', array( $this, 'delete_list' ) );
		add_action( 'admin_post_greenberry_newsletter_send_campaign', array( $this, 'send_campaign' ) );
		add_action( 'admin_post_greenberry_newsletter_send_test_campaign', array( $this, 'send_test_campaign' ) );
		add_action( 'admin_post_greenberry_newsletter_delete_campaign', array( $this, 'delete_campaign' ) );
		add_action( 'admin_post_greenberry_newsletter_create_automation', array( $this, 'create_automation' ) );
		add_action( 'admin_post_greenberry_newsletter_update_automation', array( $this, 'update_automation' ) );
		add_action( 'admin_post_greenberry_newsletter_delete_automation', array( $this, 'delete_automation' ) );
		add_action( 'admin_post_greenberry_newsletter_send_test_template', array( $this, 'send_test_template' ) );
		add_action( 'admin_post_greenberry_newsletter_delete_template', array( $this, 'delete_template' ) );
		add_action( 'admin_init', array( $this, 'redirect_hidden_post_type_lists' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_return' ) );
		add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
	}

	/**
	 * Registers Newsletter submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'greenberry',
			__( 'Newsletter', 'greenberry' ),
			__( 'Newsletter', 'greenberry' ),
			'manage_options',
			'greenberry-newsletter',
			array( $this, 'render' )
		);
	}

	/**
	 * Adds a contact manually.
	 *
	 * @return void
	 */
	public function add_contact() {
		$this->guard_action( 'greenberry_newsletter_add_contact' );

		if ( empty( $_POST['confirm_consent'] ) ) {
			$this->redirect( 'contacts', 'missing_consent' );
		}

		$result = $this->repository->upsert_contact(
			isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
			array(
				'first_name'     => isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '',
				'last_name'      => isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '',
				'tags'           => isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : 'newsletter',
				'status'         => 'subscribed',
				'consent_source' => 'admin_manual',
				'consent_text'   => __( 'Consent confirmed by a site administrator during manual entry.', 'greenberry' ),
			)
		);

		$this->redirect( 'contacts', is_wp_error( $result ) ? $result->get_error_code() : 'contact_saved' );
	}

	/**
	 * Updates a contact.
	 *
	 * @return void
	 */
	public function update_contact() {
		$this->guard_action( 'greenberry_newsletter_update_contact' );

		$result = $this->repository->update_contact(
			isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0,
			array(
				'email'      => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
				'first_name' => isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '',
				'last_name'  => isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '',
				'status'     => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'subscribed',
				'tags'       => isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '',
			)
		);

		$this->redirect( 'contacts', is_wp_error( $result ) ? $result->get_error_code() : 'contact_updated' );
	}

	/**
	 * Deletes a contact.
	 *
	 * @return void
	 */
	public function delete_contact() {
		$this->guard_action( 'greenberry_newsletter_delete_contact' );

		$deleted = $this->repository->delete_contact( isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0 );

		$this->redirect( 'contacts', $deleted ? 'contact_deleted' : 'contact_delete_failed' );
	}

	/**
	 * Imports contacts from CSV.
	 *
	 * @return void
	 */
	public function import_contacts() {
		$this->guard_action( 'greenberry_newsletter_import' );

		if ( empty( $_POST['confirm_consent'] ) ) {
			$this->redirect( 'contacts', 'missing_consent' );
		}

		if ( empty( $_FILES['contacts_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['contacts_csv']['tmp_name'] ) ) {
			$this->redirect( 'contacts', 'missing_csv' );
		}

		$imported = $this->import_csv_file( $_FILES['contacts_csv']['tmp_name'] );

		$this->redirect(
			'contacts',
			'imported',
			array(
				'imported' => absint( $imported ),
			)
		);
	}

	/**
	 * Exports contacts as CSV.
	 *
	 * @return void
	 */
	public function export_contacts() {
		$this->guard_action( 'greenberry_newsletter_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=greenberry-newsletter-contacts-' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'email', 'first_name', 'last_name', 'status', 'tags', 'consent_source', 'consent_at', 'created_at', 'unsubscribed_at' ) );

		foreach ( $this->repository->get_all_contacts_for_export() as $contact ) {
			fputcsv(
				$output,
				array(
					$contact->email,
					$contact->first_name,
					$contact->last_name,
					$contact->status,
					implode( ', ', $this->repository->get_contact_tags( $contact->id ) ),
					$contact->consent_source,
					$contact->consent_at,
					$contact->created_at,
					$contact->unsubscribed_at,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Creates a list.
	 *
	 * @return void
	 */
	public function create_list() {
		$this->guard_action( 'greenberry_newsletter_create_list' );

		$result = $this->repository->create_list(
			array(
				'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'tags'        => isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '',
				'match_mode'  => isset( $_POST['match_mode'] ) ? wp_unslash( $_POST['match_mode'] ) : 'any',
			)
		);

		$this->redirect( 'lists', is_wp_error( $result ) ? $result->get_error_code() : 'list_created' );
	}

	/**
	 * Updates a list.
	 *
	 * @return void
	 */
	public function update_list() {
		$this->guard_action( 'greenberry_newsletter_update_list' );

		$result = $this->repository->update_list(
			isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0,
			array(
				'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				'description' => isset( $_POST['description'] ) ? wp_unslash( $_POST['description'] ) : '',
				'tags'        => isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '',
				'match_mode'  => isset( $_POST['match_mode'] ) ? wp_unslash( $_POST['match_mode'] ) : 'any',
			)
		);

		$this->redirect( 'lists', is_wp_error( $result ) ? $result->get_error_code() : 'list_updated' );
	}

	/**
	 * Deletes a list.
	 *
	 * @return void
	 */
	public function delete_list() {
		$this->guard_action( 'greenberry_newsletter_delete_list' );

		$deleted = $this->repository->delete_list( isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0 );

		$this->redirect( 'lists', $deleted ? 'list_deleted' : 'list_delete_failed' );
	}

	/**
	 * Sends a campaign post to its list.
	 *
	 * @return void
	 */
	public function send_campaign() {
		$this->guard_action( 'greenberry_newsletter_send_campaign' );

		try {
			$result = $this->mailer->send_campaign_post( isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0 );
		} catch ( \Throwable $error ) {
			$this->redirect( 'campaigns', 'campaign_send_failed' );
		}

		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			$this->redirect( 'campaigns', is_wp_error( $result ) ? $result->get_error_code() : 'campaign_send_failed' );
		}

		$this->redirect(
			'campaigns',
			'campaign_sent',
			array(
				'sent'  => absint( $result['sent'] ),
				'total' => absint( $result['total'] ),
			)
		);
	}

	/**
	 * Sends a campaign post to a single test recipient.
	 *
	 * @return void
	 */
	public function send_test_campaign() {
		$this->guard_action( 'greenberry_newsletter_send_test_campaign' );

		try {
			$result = $this->mailer->send_test_campaign_post(
				isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0,
				isset( $_POST['test_recipient'] ) ? wp_unslash( $_POST['test_recipient'] ) : ''
			);
		} catch ( \Throwable $error ) {
			$result = new \WP_Error( 'test_send_failed', __( 'The test email could not be sent.', 'greenberry' ) );
		}

		$this->redirect( 'campaigns', is_wp_error( $result ) ? $result->get_error_code() : 'campaign_test_sent' );
	}

	/**
	 * Moves a campaign to the bin.
	 *
	 * @return void
	 */
	public function delete_campaign() {
		$this->delete_post_type_item(
			'greenberry_newsletter_delete_campaign',
			Campaign_Post_Type::POST_TYPE,
			'campaigns',
			'campaign_deleted'
		);
	}

	/**
	 * Creates an automation.
	 *
	 * @return void
	 */
	public function create_automation() {
		$this->guard_action( 'greenberry_newsletter_create_automation' );

		$result = $this->repository->create_automation(
			array(
				'name'         => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				'trigger_type' => isset( $_POST['trigger_type'] ) ? wp_unslash( $_POST['trigger_type'] ) : '',
				'post_types'   => isset( $_POST['post_types'] ) ? wp_unslash( $_POST['post_types'] ) : 'post',
				'list_id'      => isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0,
				'subject'      => isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
				'settings'     => array(
					'template_id' => isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0,
					'categories'  => isset( $_POST['categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['categories'] ) ) : array(),
				),
			)
		);

		$this->redirect( 'automations', is_wp_error( $result ) ? $result->get_error_code() : 'automation_created' );
	}

	/**
	 * Updates an automation.
	 *
	 * @return void
	 */
	public function update_automation() {
		$this->guard_action( 'greenberry_newsletter_update_automation' );

		$result = $this->repository->update_automation(
			isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0,
			array(
				'name'         => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				'trigger_type' => isset( $_POST['trigger_type'] ) ? wp_unslash( $_POST['trigger_type'] ) : '',
				'post_types'   => isset( $_POST['post_types'] ) ? wp_unslash( $_POST['post_types'] ) : 'post',
				'list_id'      => isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0,
				'subject'      => isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
				'settings'     => array(
					'template_id' => isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0,
					'categories'  => isset( $_POST['categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['categories'] ) ) : array(),
				),
			)
		);

		$this->redirect( 'automations', is_wp_error( $result ) ? $result->get_error_code() : 'automation_updated' );
	}

	/**
	 * Deletes an automation.
	 *
	 * @return void
	 */
	public function delete_automation() {
		$this->guard_action( 'greenberry_newsletter_delete_automation' );

		$deleted = $this->repository->delete_automation( isset( $_POST['automation_id'] ) ? absint( $_POST['automation_id'] ) : 0 );

		$this->redirect( 'automations', $deleted ? 'automation_deleted' : 'automation_delete_failed' );
	}

	/**
	 * Sends a reusable template to a single test recipient.
	 *
	 * @return void
	 */
	public function send_test_template() {
		$this->guard_action( 'greenberry_newsletter_send_test_template' );

		try {
			$result = $this->mailer->send_test_template(
				isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0,
				isset( $_POST['test_recipient'] ) ? wp_unslash( $_POST['test_recipient'] ) : ''
			);
		} catch ( \Throwable $error ) {
			$result = new \WP_Error( 'test_send_failed', __( 'The test email could not be sent.', 'greenberry' ) );
		}

		$this->redirect( 'templates', is_wp_error( $result ) ? $result->get_error_code() : 'template_test_sent' );
	}

	/**
	 * Moves a template to the bin.
	 *
	 * @return void
	 */
	public function delete_template() {
		$this->delete_post_type_item(
			'greenberry_newsletter_delete_template',
			Email_Template_Post_Type::POST_TYPE,
			'templates',
			'template_deleted'
		);
	}

	/**
	 * Renders the Newsletter admin page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'contacts';
		if ( ! in_array( $tab, array( 'contacts', 'lists', 'campaigns', 'automations', 'templates' ), true ) ) {
			$tab = 'contacts';
		}

		\Greenberry\Admin_UI::open(
			__( 'Newsletter', 'greenberry' ),
			__( 'Collect consented subscribers, organise lists with tags, and send campaigns or automations.', 'greenberry' )
		);
		$this->render_notice();
		$this->render_tabs( $tab );

		if ( 'contacts' === $tab ) {
			$this->render_contacts_tab();
		} elseif ( 'lists' === $tab ) {
			$this->render_lists_tab();
		} elseif ( 'campaigns' === $tab ) {
			$this->render_campaigns_tab();
		} elseif ( 'automations' === $tab ) {
			$this->render_automations_tab();
		} elseif ( 'templates' === $tab ) {
			$this->render_templates_tab();
		}

		\Greenberry\Admin_UI::close();
	}

	/**
	 * Renders contacts tab.
	 *
	 * @return void
	 */
	private function render_contacts_tab() {
		$contacts        = $this->repository->get_contacts( array( 'limit' => 50 ) );
		$tags_by_contact = $this->repository->get_contact_tags_map( wp_list_pluck( $contacts, 'id' ) );
		$edit_contact_id = isset( $_GET['edit_contact'] ) ? absint( $_GET['edit_contact'] ) : 0;
		$editing_contact = $edit_contact_id ? $this->repository->get_contact( $edit_contact_id ) : null;
		$is_editing      = (bool) $editing_contact;
		$contact_tags    = $editing_contact ? implode( ', ', $this->repository->get_contact_tags( $editing_contact->id ) ) : 'newsletter';
		$statuses        = array(
			'subscribed'   => __( 'Subscribed', 'greenberry' ),
			'pending'      => __( 'Pending', 'greenberry' ),
			'unsubscribed' => __( 'Unsubscribed', 'greenberry' ),
			'bounced'      => __( 'Bounced', 'greenberry' ),
		);
		?>
		<div class="greenberry-grid">
			<div class="greenberry-panel">
				<h2><?php echo esc_html( $is_editing ? __( 'Edit Contact', 'greenberry' ) : __( 'Add Contact', 'greenberry' ) ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $is_editing ? 'greenberry_newsletter_update_contact' : 'greenberry_newsletter_add_contact' ); ?>">
					<?php if ( $is_editing ) : ?>
						<input type="hidden" name="contact_id" value="<?php echo esc_attr( $editing_contact->id ); ?>">
						<?php wp_nonce_field( 'greenberry_newsletter_update_contact' ); ?>
					<?php else : ?>
						<?php wp_nonce_field( 'greenberry_newsletter_add_contact' ); ?>
					<?php endif; ?>
					<div class="greenberry-field">
						<label for="greenberry-contact-email"><?php esc_html_e( 'Email', 'greenberry' ); ?></label>
						<input id="greenberry-contact-email" type="email" name="email" value="<?php echo esc_attr( $is_editing ? $editing_contact->email : '' ); ?>" required>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-contact-first-name"><?php esc_html_e( 'First name', 'greenberry' ); ?></label>
						<input id="greenberry-contact-first-name" type="text" name="first_name" value="<?php echo esc_attr( $is_editing ? $editing_contact->first_name : '' ); ?>">
					</div>
					<div class="greenberry-field">
						<label for="greenberry-contact-last-name"><?php esc_html_e( 'Last name', 'greenberry' ); ?></label>
						<input id="greenberry-contact-last-name" type="text" name="last_name" value="<?php echo esc_attr( $is_editing ? $editing_contact->last_name : '' ); ?>">
					</div>
					<div class="greenberry-field">
						<label for="greenberry-contact-tags"><?php esc_html_e( 'Tags', 'greenberry' ); ?></label>
						<input id="greenberry-contact-tags" type="text" name="tags" value="<?php echo esc_attr( $contact_tags ); ?>">
					</div>
					<?php if ( $is_editing ) : ?>
						<div class="greenberry-field">
							<label for="greenberry-contact-status"><?php esc_html_e( 'Status', 'greenberry' ); ?></label>
							<select id="greenberry-contact-status" name="status">
								<?php foreach ( $statuses as $status => $label ) : ?>
									<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $editing_contact->status, $status ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php else : ?>
						<label class="greenberry-field">
							<input type="checkbox" name="confirm_consent" value="1" required>
							<?php esc_html_e( 'Consent to store and email this contact has been confirmed.', 'greenberry' ); ?>
						</label>
					<?php endif; ?>
					<div class="greenberry-actions">
						<?php submit_button( $is_editing ? __( 'Update Contact', 'greenberry' ) : __( 'Save Contact', 'greenberry' ), 'primary', 'submit', false ); ?>
						<?php if ( $is_editing ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( $this->tab_url( 'contacts' ) ); ?>"><?php esc_html_e( 'Cancel', 'greenberry' ); ?></a>
						<?php endif; ?>
					</div>
				</form>

				<hr>

				<h2><?php esc_html_e( 'Import CSV', 'greenberry' ); ?></h2>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="greenberry_newsletter_import">
					<?php wp_nonce_field( 'greenberry_newsletter_import' ); ?>
					<div class="greenberry-field">
						<label for="greenberry-contacts-csv"><?php esc_html_e( 'CSV file', 'greenberry' ); ?></label>
						<input id="greenberry-contacts-csv" type="file" name="contacts_csv" accept=".csv,text/csv" required>
						<p class="description"><?php esc_html_e( 'Columns: email, first_name, last_name, tags.', 'greenberry' ); ?></p>
					</div>
					<label class="greenberry-field">
						<input type="checkbox" name="confirm_consent" value="1" required>
						<?php esc_html_e( 'Imported contacts have consented to receive email updates.', 'greenberry' ); ?>
					</label>
					<?php submit_button( __( 'Import Contacts', 'greenberry' ) ); ?>
				</form>

				<hr>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="greenberry_newsletter_export">
					<?php wp_nonce_field( 'greenberry_newsletter_export' ); ?>
					<?php submit_button( __( 'Export Contacts CSV', 'greenberry' ), 'secondary' ); ?>
				</form>
			</div>

			<div class="greenberry-panel">
				<h2>
					<?php
					printf(
						/* translators: %d: contact count. */
						esc_html__( 'Contacts (%d)', 'greenberry' ),
						absint( $this->repository->count_contacts() )
					);
					?>
				</h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Email', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Name', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Status', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Tags', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Added', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'greenberry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $contacts ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No contacts yet.', 'greenberry' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $contacts as $contact ) : ?>
							<tr>
								<td><?php echo esc_html( $contact->email ); ?></td>
								<td><?php echo esc_html( trim( $contact->first_name . ' ' . $contact->last_name ) ); ?></td>
								<td><?php echo esc_html( isset( $statuses[ $contact->status ] ) ? $statuses[ $contact->status ] : ucfirst( $contact->status ) ); ?></td>
								<td><?php $this->render_tag_badges( isset( $tags_by_contact[ $contact->id ] ) ? $tags_by_contact[ $contact->id ] : array(), __( 'No tags', 'greenberry' ) ); ?></td>
								<td><?php echo esc_html( $contact->created_at ); ?></td>
								<td>
									<div class="greenberry-actions">
										<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit_contact', absint( $contact->id ), $this->tab_url( 'contacts' ) ) ); ?>"><?php esc_html_e( 'Edit', 'greenberry' ); ?></a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form" onsubmit="return confirm( '<?php echo esc_js( __( 'Delete this contact?', 'greenberry' ) ); ?>' );">
											<input type="hidden" name="action" value="greenberry_newsletter_delete_contact">
											<input type="hidden" name="contact_id" value="<?php echo esc_attr( $contact->id ); ?>">
											<?php wp_nonce_field( 'greenberry_newsletter_delete_contact' ); ?>
											<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'greenberry' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders lists tab.
	 *
	 * @return void
	 */
	private function render_lists_tab() {
		$lists        = $this->repository->get_lists();
		$edit_list_id = isset( $_GET['edit_list'] ) ? absint( $_GET['edit_list'] ) : 0;
		$editing_list = $edit_list_id ? $this->repository->get_list( $edit_list_id ) : null;
		$is_editing   = (bool) $editing_list;
		$list_tags    = $editing_list ? implode( ', ', $this->repository->get_list_tag_slugs( $editing_list ) ) : '';
		?>
		<div class="greenberry-grid">
			<div class="greenberry-panel">
				<h2><?php echo esc_html( $is_editing ? __( 'Edit List', 'greenberry' ) : __( 'Create List', 'greenberry' ) ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $is_editing ? 'greenberry_newsletter_update_list' : 'greenberry_newsletter_create_list' ); ?>">
					<?php if ( $is_editing ) : ?>
						<input type="hidden" name="list_id" value="<?php echo esc_attr( $editing_list->id ); ?>">
						<?php wp_nonce_field( 'greenberry_newsletter_update_list' ); ?>
					<?php else : ?>
						<?php wp_nonce_field( 'greenberry_newsletter_create_list' ); ?>
					<?php endif; ?>
					<div class="greenberry-field">
						<label for="greenberry-list-name"><?php esc_html_e( 'Name', 'greenberry' ); ?></label>
						<input id="greenberry-list-name" type="text" name="name" value="<?php echo esc_attr( $is_editing ? $editing_list->name : '' ); ?>" required>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-list-description"><?php esc_html_e( 'Description', 'greenberry' ); ?></label>
						<textarea id="greenberry-list-description" name="description" rows="3"><?php echo esc_textarea( $is_editing ? $editing_list->description : '' ); ?></textarea>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-list-tags"><?php esc_html_e( 'Tags', 'greenberry' ); ?></label>
						<input id="greenberry-list-tags" type="text" name="tags" value="<?php echo esc_attr( $list_tags ); ?>" placeholder="newsletter, members">
					</div>
					<div class="greenberry-field">
						<label for="greenberry-list-match-mode"><?php esc_html_e( 'Match mode', 'greenberry' ); ?></label>
						<select id="greenberry-list-match-mode" name="match_mode">
							<option value="any" <?php selected( $is_editing ? $editing_list->match_mode : 'any', 'any' ); ?>><?php esc_html_e( 'Any listed tag', 'greenberry' ); ?></option>
							<option value="all" <?php selected( $is_editing ? $editing_list->match_mode : 'any', 'all' ); ?>><?php esc_html_e( 'All listed tags', 'greenberry' ); ?></option>
						</select>
					</div>
					<div class="greenberry-actions">
						<?php submit_button( $is_editing ? __( 'Update List', 'greenberry' ) : __( 'Create List', 'greenberry' ), 'primary', 'submit', false ); ?>
						<?php if ( $is_editing ) : ?>
							<a class="button button-secondary" href="<?php echo esc_url( $this->tab_url( 'lists' ) ); ?>"><?php esc_html_e( 'Cancel', 'greenberry' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'Lists', 'greenberry' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Tags', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Mode', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Contacts', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'greenberry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $lists ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No lists yet.', 'greenberry' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $lists as $list ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $list->name ); ?></strong></td>
								<td><?php $this->render_tag_badges( $this->repository->get_list_tag_slugs( $list ), __( 'All subscribers', 'greenberry' ) ); ?></td>
								<td><?php echo esc_html( 'all' === $list->match_mode ? __( 'All', 'greenberry' ) : __( 'Any', 'greenberry' ) ); ?></td>
								<td><?php echo esc_html( $this->repository->count_contacts_for_list( $list->id ) ); ?></td>
								<td>
									<div class="greenberry-actions">
										<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit_list', absint( $list->id ), $this->tab_url( 'lists' ) ) ); ?>"><?php esc_html_e( 'Edit', 'greenberry' ); ?></a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form" onsubmit="return confirm( '<?php echo esc_js( __( 'Delete this list?', 'greenberry' ) ); ?>' );">
											<input type="hidden" name="action" value="greenberry_newsletter_delete_list">
											<input type="hidden" name="list_id" value="<?php echo esc_attr( $list->id ); ?>">
											<?php wp_nonce_field( 'greenberry_newsletter_delete_list' ); ?>
											<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'greenberry' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders campaigns tab.
	 *
	 * @return void
	 */
	private function render_campaigns_tab() {
		$campaigns = get_posts(
			array(
				'post_type'        => Campaign_Post_Type::POST_TYPE,
				'post_status'      => array( 'draft', 'pending', 'future', 'private', 'publish' ),
				'numberposts'      => 50,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);
		$new_url      = $this->editor_url( Campaign_Post_Type::POST_TYPE, 'campaigns' );
		$test_default = sanitize_email( get_option( 'admin_email' ) );
		?>
		<div class="greenberry-panel">
			<div class="greenberry-section-heading">
				<h2><?php esc_html_e( 'Campaigns', 'greenberry' ); ?></h2>
				<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add Campaign', 'greenberry' ); ?></a>
			</div>
			<p class="greenberry-muted"><?php esc_html_e( 'Design each campaign in the WordPress block editor, set its subject and list in the Email delivery panel, then send a test or send to the list.', 'greenberry' ); ?></p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Campaign', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'List', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'Status', 'greenberry' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'greenberry' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $campaigns ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No campaigns yet. Use Add Campaign to design your first email.', 'greenberry' ); ?></td></tr>
					<?php endif; ?>

					<?php foreach ( $campaigns as $campaign ) : ?>
						<?php
						$subject  = (string) get_post_meta( $campaign->ID, Campaign_Post_Type::META_SUBJECT, true );
						$list_id  = absint( get_post_meta( $campaign->ID, Campaign_Post_Type::META_LIST_ID, true ) );
						$sent_at  = (string) get_post_meta( $campaign->ID, Campaign_Post_Type::META_SENT_AT, true );
						$list     = $list_id ? $this->repository->get_list( $list_id ) : null;
						$edit_url = $this->editor_url( Campaign_Post_Type::POST_TYPE, 'campaigns', $campaign->ID );
						?>
						<tr>
							<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $campaign ) ); ?></a></strong></td>
							<td><?php echo esc_html( $subject ); ?></td>
							<td><?php echo esc_html( $list ? $list->name : __( 'All subscribers', 'greenberry' ) ); ?></td>
							<td>
								<?php if ( '' !== $sent_at ) : ?>
									<span class="greenberry-status is-ready"><?php esc_html_e( 'Sent', 'greenberry' ); ?></span>
								<?php else : ?>
									<span class="greenberry-status"><?php esc_html_e( 'Draft', 'greenberry' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<div class="greenberry-actions">
									<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'greenberry' ); ?></a>

									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form">
										<input type="hidden" name="action" value="greenberry_newsletter_send_test_campaign">
										<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign->ID ); ?>">
										<input type="hidden" name="test_recipient" value="<?php echo esc_attr( $test_default ); ?>">
										<?php wp_nonce_field( 'greenberry_newsletter_send_test_campaign' ); ?>
										<button type="submit" class="button button-small"><?php esc_html_e( 'Send test', 'greenberry' ); ?></button>
									</form>

									<?php if ( '' === $sent_at ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form" onsubmit="return confirm( '<?php echo esc_js( __( 'Send this campaign to the selected list now?', 'greenberry' ) ); ?>' );">
											<input type="hidden" name="action" value="greenberry_newsletter_send_campaign">
											<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign->ID ); ?>">
											<?php wp_nonce_field( 'greenberry_newsletter_send_campaign' ); ?>
											<button type="submit" class="button button-primary button-small"><?php esc_html_e( 'Send now', 'greenberry' ); ?></button>
										</form>
									<?php endif; ?>

									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form" onsubmit="return confirm( '<?php echo esc_js( __( 'Delete this campaign?', 'greenberry' ) ); ?>' );">
										<input type="hidden" name="action" value="greenberry_newsletter_delete_campaign">
										<input type="hidden" name="post_id" value="<?php echo esc_attr( $campaign->ID ); ?>">
										<?php wp_nonce_field( 'greenberry_newsletter_delete_campaign' ); ?>
										<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'greenberry' ); ?></button>
									</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders automations tab.
	 *
	 * @return void
	 */
	private function render_automations_tab() {
		$automations        = $this->repository->get_automations();
		$templates          = Email_Template_Post_Type::options();
		$edit_automation_id = isset( $_GET['edit_automation'] ) ? absint( $_GET['edit_automation'] ) : 0;
		$editing_automation = $edit_automation_id ? $this->repository->get_automation( $edit_automation_id ) : null;
		$is_editing         = (bool) $editing_automation;
		$selected_template   = $is_editing ? $this->repository->get_automation_template_id( $editing_automation ) : 0;
		$selected_post_types = $is_editing ? $this->repository->get_automation_post_types( $editing_automation ) : array( 'post' );
		$all_post_types      = get_post_types( array( 'public' => true ), 'objects' );
		unset( $all_post_types['attachment'] );
		$selected_categories = $is_editing ? $this->repository->get_automation_categories( $editing_automation ) : array();
		$all_categories      = get_categories( array( 'hide_empty' => false, 'number' => 200 ) );
		?>
		<div class="greenberry-grid">
			<div class="greenberry-panel">
				<h2><?php echo esc_html( $is_editing ? __( 'Edit Automation', 'greenberry' ) : __( 'Create Automation', 'greenberry' ) ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $is_editing ? 'greenberry_newsletter_update_automation' : 'greenberry_newsletter_create_automation' ); ?>">
					<?php if ( $is_editing ) : ?>
						<input type="hidden" name="automation_id" value="<?php echo esc_attr( $editing_automation->id ); ?>">
						<?php wp_nonce_field( 'greenberry_newsletter_update_automation' ); ?>
					<?php else : ?>
						<?php wp_nonce_field( 'greenberry_newsletter_create_automation' ); ?>
					<?php endif; ?>
					<div class="greenberry-field">
						<label for="greenberry-automation-name"><?php esc_html_e( 'Name', 'greenberry' ); ?></label>
						<input id="greenberry-automation-name" type="text" name="name" value="<?php echo esc_attr( $is_editing ? $editing_automation->name : '' ); ?>" required>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-automation-trigger"><?php esc_html_e( 'Trigger', 'greenberry' ); ?></label>
						<?php $current_trigger = $is_editing ? $editing_automation->trigger_type : 'weekly_digest'; ?>
						<select id="greenberry-automation-trigger" name="trigger_type">
							<option value="daily_digest" <?php selected( $current_trigger, 'daily_digest' ); ?>><?php esc_html_e( 'Daily digest', 'greenberry' ); ?></option>
							<option value="weekly_digest" <?php selected( $current_trigger, 'weekly_digest' ); ?>><?php esc_html_e( 'Weekly digest', 'greenberry' ); ?></option>
							<option value="monthly_digest" <?php selected( $current_trigger, 'monthly_digest' ); ?>><?php esc_html_e( 'Monthly digest', 'greenberry' ); ?></option>
							<option value="yearly_digest" <?php selected( $current_trigger, 'yearly_digest' ); ?>><?php esc_html_e( 'Yearly digest', 'greenberry' ); ?></option>
							<option value="post_publish" <?php selected( $current_trigger, 'post_publish' ); ?>><?php esc_html_e( 'When a post is published', 'greenberry' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Digests send the latest posts on a schedule; "When a post is published" sends immediately for each new post.', 'greenberry' ); ?></p>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-automation-list"><?php esc_html_e( 'List', 'greenberry' ); ?></label>
						<?php $this->render_list_select( 'greenberry-automation-list', $is_editing ? absint( $editing_automation->list_id ) : 0 ); ?>
					</div>
					<div class="greenberry-field">
						<label><?php esc_html_e( 'Post types', 'greenberry' ); ?></label>
						<div class="greenberry-checkbox-grid">
							<?php foreach ( $all_post_types as $post_type ) : ?>
								<label>
									<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $selected_post_types, true ) ); ?>>
									<?php echo esc_html( isset( $post_type->labels->singular_name ) && $post_type->labels->singular_name ? $post_type->labels->singular_name : $post_type->name ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Controls which content triggers or fills the email.', 'greenberry' ); ?></p>
					</div>
					<?php if ( ! empty( $all_categories ) ) : ?>
						<div class="greenberry-field">
							<label><?php esc_html_e( 'Categories', 'greenberry' ); ?></label>
							<div class="greenberry-checkbox-grid greenberry-checkbox-grid--scroll">
								<?php foreach ( $all_categories as $category ) : ?>
									<label>
										<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $category->term_id ); ?>" <?php checked( in_array( absint( $category->term_id ), $selected_categories, true ) ); ?>>
										<?php echo esc_html( $category->name ); ?>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="description"><?php esc_html_e( 'Optional. Leave none selected for all categories. Applies to standard posts only.', 'greenberry' ); ?></p>
						</div>
					<?php endif; ?>
					<div class="greenberry-field">
						<label for="greenberry-automation-subject"><?php esc_html_e( 'Subject', 'greenberry' ); ?></label>
						<input id="greenberry-automation-subject" type="text" name="subject" value="<?php echo esc_attr( $is_editing ? $editing_automation->subject : __( '{site_name} updates', 'greenberry' ) ); ?>" required>
						<?php Email_Template::render_placeholder_picker( 'greenberry-automation-subject' ); ?>
					</div>
					<div class="greenberry-field">
							<label for="greenberry-automation-template"><?php esc_html_e( 'Template', 'greenberry' ); ?></label>
							<select id="greenberry-automation-template" name="template_id">
								<option value="0" <?php selected( $selected_template, 0 ); ?>><?php esc_html_e( 'Default layout', 'greenberry' ); ?></option>
								<?php foreach ( $templates as $template_id => $title ) : ?>
									<option value="<?php echo esc_attr( $template_id ); ?>" <?php selected( $selected_template, absint( $template_id ) ); ?>><?php echo esc_html( $title ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Design templates in the Templates tab. Use the "Latest Posts (Email)" block (or a {posts} placeholder) to show recent posts.', 'greenberry' ); ?></p>
						</div>
						<div class="greenberry-actions">
							<?php submit_button( $is_editing ? __( 'Update Automation', 'greenberry' ) : __( 'Create Automation', 'greenberry' ), 'primary', 'submit', false ); ?>
							<?php if ( $is_editing ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( $this->tab_url( 'automations' ) ); ?>"><?php esc_html_e( 'Cancel', 'greenberry' ); ?></a>
							<?php endif; ?>
						</div>
				</form>
			</div>

			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'Automations', 'greenberry' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Trigger', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Template', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Post types', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Last sent', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'greenberry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $automations ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No automations yet.', 'greenberry' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $automations as $automation ) : ?>
							<?php $template_id = $this->repository->get_automation_template_id( $automation ); ?>
							<tr>
								<td><strong><?php echo esc_html( $automation->name ); ?></strong></td>
								<td><?php echo esc_html( str_replace( '_', ' ', ucfirst( $automation->trigger_type ) ) ); ?></td>
								<td><?php echo esc_html( $template_id && isset( $templates[ $template_id ] ) ? $templates[ $template_id ] : __( 'Default layout', 'greenberry' ) ); ?></td>
								<td><?php echo esc_html( implode( ', ', $this->repository->get_automation_post_types( $automation ) ) ); ?></td>
								<td><?php echo esc_html( $automation->last_sent_at ); ?></td>
								<td>
									<div class="greenberry-actions">
										<a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit_automation', absint( $automation->id ), $this->tab_url( 'automations' ) ) ); ?>"><?php esc_html_e( 'Edit', 'greenberry' ); ?></a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form" onsubmit="return confirm( '<?php echo esc_js( __( 'Delete this automation?', 'greenberry' ) ); ?>' );">
											<input type="hidden" name="action" value="greenberry_newsletter_delete_automation">
											<input type="hidden" name="automation_id" value="<?php echo esc_attr( $automation->id ); ?>">
											<?php wp_nonce_field( 'greenberry_newsletter_delete_automation' ); ?>
											<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'greenberry' ); ?></button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the templates tab.
	 *
	 * @return void
	 */
	private function render_templates_tab() {
		$templates = get_posts(
			array(
				'post_type'        => Email_Template_Post_Type::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts'      => 100,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);
		$new_url = $this->editor_url( Email_Template_Post_Type::POST_TYPE, 'templates' );
		?>
		<div class="greenberry-panel">
			<div class="greenberry-section-heading">
				<h2><?php esc_html_e( 'Email templates', 'greenberry' ); ?></h2>
				<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add Template', 'greenberry' ); ?></a>
			</div>
			<p class="greenberry-muted"><?php esc_html_e( 'Design reusable email layouts in the block editor with images, headings, and buttons. Add the "Latest Posts (Email)" block where recent posts should appear, then use Send test to preview it by email.', 'greenberry' ); ?></p>

			<?php if ( empty( $templates ) ) : ?>
				<p><?php esc_html_e( 'No templates yet. Add one, then choose it on a weekly or daily digest automation.', 'greenberry' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Template', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Status', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'greenberry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $templates as $template ) : ?>
							<?php $edit_url = $this->editor_url( Email_Template_Post_Type::POST_TYPE, 'templates', $template->ID ); ?>
							<tr>
								<td><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $template ) ); ?></a></strong></td>
								<td><?php echo esc_html( 'publish' === $template->post_status ? __( 'Published', 'greenberry' ) : __( 'Draft', 'greenberry' ) ); ?></td>
								<td>
									<div class="greenberry-actions">
										<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'greenberry' ); ?></a>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form">
											<input type="hidden" name="action" value="greenberry_newsletter_send_test_template">
											<input type="hidden" name="template_id" value="<?php echo esc_attr( $template->ID ); ?>">
											<input type="hidden" name="test_recipient" value="<?php echo esc_attr( sanitize_email( get_option( 'admin_email' ) ) ); ?>">
											<?php wp_nonce_field( 'greenberry_newsletter_send_test_template' ); ?>
											<button type="submit" class="button button-small"><?php esc_html_e( 'Send test', 'greenberry' ); ?></button>
										</form>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-inline-form" onsubmit="return confirm( '<?php echo esc_js( __( 'Delete this template?', 'greenberry' ) ); ?>' );">
											<input type="hidden" name="action" value="greenberry_newsletter_delete_template">
											<input type="hidden" name="post_id" value="<?php echo esc_attr( $template->ID ); ?>">
											<?php wp_nonce_field( 'greenberry_newsletter_delete_template' ); ?>
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
	}

	/**
	 * Imports a CSV file.
	 *
	 * @param string $path Uploaded CSV path.
	 * @return int
	 */
	private function import_csv_file( $path ) {
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			return 0;
		}

		$imported = 0;
		$first    = fgetcsv( $handle );
		if ( false === $first ) {
			fclose( $handle );
			return 0;
		}

		$headers = array_map( 'sanitize_key', $first );
		$has_header = in_array( 'email', $headers, true );

		if ( ! $has_header ) {
			$headers = array( 'email', 'first_name', 'last_name', 'tags' );
			$row     = $first;
			$result  = $this->import_csv_row( $headers, $row );
			if ( $result ) {
				++$imported;
			}
		}

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$result = $this->import_csv_row( $headers, $row );
			if ( $result ) {
				++$imported;
			}
		}

		fclose( $handle );

		return $imported;
	}

	/**
	 * Imports one CSV row.
	 *
	 * @param array<int,string> $headers Headers.
	 * @param array<int,string> $row Row.
	 * @return bool
	 */
	private function import_csv_row( $headers, $row ) {
		$data = array();
		foreach ( $headers as $index => $header ) {
			$data[ $header ] = isset( $row[ $index ] ) ? $row[ $index ] : '';
		}

		if ( empty( $data['email'] ) ) {
			return false;
		}

		$result = $this->repository->upsert_contact(
			$data['email'],
			array(
				'first_name'     => isset( $data['first_name'] ) ? $data['first_name'] : '',
				'last_name'      => isset( $data['last_name'] ) ? $data['last_name'] : '',
				'tags'           => isset( $data['tags'] ) ? $data['tags'] : 'newsletter',
				'status'         => 'subscribed',
				'consent_source' => 'csv_import',
				'consent_text'   => __( 'Consent confirmed by a site administrator during CSV import.', 'greenberry' ),
			)
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Renders tab navigation.
	 *
	 * @param string $active Active tab.
	 * @return void
	 */
	private function render_tabs( $active ) {
		$tabs = array(
			'contacts'    => __( 'Contacts', 'greenberry' ),
			'lists'       => __( 'Lists', 'greenberry' ),
			'campaigns'   => __( 'Campaigns', 'greenberry' ),
			'automations' => __( 'Automations', 'greenberry' ),
			'templates'   => __( 'Templates', 'greenberry' ),
		);
		?>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Newsletter sections', 'greenberry' ); ?>">
			<?php foreach ( $tabs as $tab => $label ) : ?>
				<a class="nav-tab <?php echo esc_attr( $active === $tab ? 'nav-tab-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'greenberry-newsletter', 'tab' => $tab ), admin_url( 'admin.php' ) ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
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

		$notice = sanitize_key( $_GET['greenberry_notice'] );
		$messages = array(
			'contact_saved'              => __( 'Contact saved.', 'greenberry' ),
			'contact_updated'            => __( 'Contact updated.', 'greenberry' ),
			'contact_deleted'            => __( 'Contact deleted.', 'greenberry' ),
			'missing_consent'           => __( 'Consent confirmation is required.', 'greenberry' ),
			'missing_csv'               => __( 'Please choose a CSV file.', 'greenberry' ),
			'imported'                  => sprintf(
				/* translators: %d: imported contacts. */
				__( 'Imported %d contacts.', 'greenberry' ),
				isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0
			),
			'list_created'              => __( 'List created.', 'greenberry' ),
			'list_updated'              => __( 'List updated.', 'greenberry' ),
			'list_deleted'              => __( 'List deleted.', 'greenberry' ),
			'campaign_created'          => __( 'Campaign saved.', 'greenberry' ),
			'campaign_deleted'          => __( 'Campaign deleted.', 'greenberry' ),
			'campaign_test_sent'        => __( 'Test campaign sent.', 'greenberry' ),
			'campaign_sent'             => sprintf(
				/* translators: 1: sent count, 2: total count. */
				__( 'Campaign sent to %1$d of %2$d contacts.', 'greenberry' ),
				isset( $_GET['sent'] ) ? absint( $_GET['sent'] ) : 0,
				isset( $_GET['total'] ) ? absint( $_GET['total'] ) : 0
			),
			'automation_created'        => __( 'Automation created.', 'greenberry' ),
			'automation_updated'        => __( 'Automation updated.', 'greenberry' ),
			'automation_deleted'        => __( 'Automation deleted.', 'greenberry' ),
			'template_deleted'          => __( 'Template deleted.', 'greenberry' ),
			'template_test_sent'        => __( 'Test email sent.', 'greenberry' ),
			'template_not_found'        => __( 'That template could not be found.', 'greenberry' ),
			'invalid_email'             => __( 'Please enter a valid email address.', 'greenberry' ),
			'contact_not_found'         => __( 'That contact could not be found.', 'greenberry' ),
			'contact_insert_failed'     => __( 'Could not save the contact.', 'greenberry' ),
			'contact_update_failed'     => __( 'Could not update the contact.', 'greenberry' ),
			'contact_delete_failed'     => __( 'Could not delete the contact.', 'greenberry' ),
			'missing_list_name'         => __( 'List name is required.', 'greenberry' ),
			'list_not_found'            => __( 'That list could not be found.', 'greenberry' ),
			'list_insert_failed'        => __( 'Could not create the list. The name may already exist.', 'greenberry' ),
			'list_update_failed'        => __( 'Could not update the list. The name may already exist.', 'greenberry' ),
			'list_delete_failed'        => __( 'Could not delete the list.', 'greenberry' ),
			'missing_campaign_fields'   => __( 'Campaign name and subject are required.', 'greenberry' ),
			'campaign_insert_failed'    => __( 'Could not create the campaign.', 'greenberry' ),
			'campaign_send_failed'      => __( 'The campaign could not be sent.', 'greenberry' ),
			'campaign_render_failed'    => __( 'The campaign content could not be rendered.', 'greenberry' ),
			'invalid_test_recipient'    => __( 'Please enter a valid test recipient email address.', 'greenberry' ),
			'test_send_failed'          => __( 'The test email could not be sent.', 'greenberry' ),
			'campaign_not_found'        => __( 'That campaign could not be found.', 'greenberry' ),
			'missing_automation_fields' => __( 'Automation name, trigger, and subject are required.', 'greenberry' ),
			'invalid_automation_trigger' => __( 'Invalid automation trigger.', 'greenberry' ),
			'automation_not_found'      => __( 'That automation could not be found.', 'greenberry' ),
			'automation_update_failed'  => __( 'Could not update the automation.', 'greenberry' ),
			'automation_delete_failed'  => __( 'Could not delete the automation.', 'greenberry' ),
			'template_render_failed'    => __( 'The email template could not be rendered.', 'greenberry' ),
			'post_not_found'            => __( 'That item could not be found.', 'greenberry' ),
			'post_delete_failed'        => __( 'Could not delete the item.', 'greenberry' ),
		);

		$message  = isset( $messages[ $notice ] ) ? $messages[ $notice ] : __( 'Action complete.', 'greenberry' );
		$is_error = false !== strpos( $notice, 'failed' )
			|| false !== strpos( $notice, 'missing' )
			|| false !== strpos( $notice, 'not_found' )
			|| 0 === strpos( $notice, 'invalid_' );
		$type     = $is_error ? 'error' : 'success';
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders tag badges.
	 *
	 * @param array<int,string> $tags Tags.
	 * @param string            $empty_label Empty label.
	 * @return void
	 */
	private function render_tag_badges( $tags, $empty_label = '' ) {
		if ( empty( $tags ) ) {
			echo '<span class="greenberry-muted">' . esc_html( $empty_label ? $empty_label : __( 'None', 'greenberry' ) ) . '</span>';
			return;
		}

		foreach ( $tags as $tag ) {
			echo '<span class="greenberry-badge">' . esc_html( $tag ) . '</span> ';
		}
	}

	/**
	 * Renders a list select.
	 *
	 * @param string $id Field ID.
	 * @return void
	 */
	private function render_list_select( $id, $selected = 0 ) {
		$lists = $this->repository->get_lists();
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="list_id">
			<option value="0" <?php selected( absint( $selected ), 0 ); ?>><?php esc_html_e( 'All subscribed contacts', 'greenberry' ); ?></option>
			<?php foreach ( $lists as $list ) : ?>
				<option value="<?php echo esc_attr( $list->id ); ?>" <?php selected( absint( $selected ), absint( $list->id ) ); ?>><?php echo esc_html( $list->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Redirects hidden native CPT list screens to the Greenberry tab.
	 *
	 * @return void
	 */
	public function redirect_hidden_post_type_lists() {
		global $pagenow;

		if ( 'edit.php' !== $pagenow || ! empty( $_GET['greenberry_allow_native'] ) ) {
			return;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
		$tabs      = $this->editor_post_type_tabs();

		if ( isset( $tabs[ $post_type ] ) ) {
			wp_safe_redirect( $this->tab_url( $tabs[ $post_type ] ) );
			exit;
		}
	}

	/**
	 * Loads the shared admin script on Greenberry editor screens.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_editor_return( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) ) {
			return;
		}

		$tabs = $this->editor_post_type_tabs();
		if ( ! isset( $tabs[ $screen->post_type ] ) ) {
			return;
		}

		$return_url = isset( $_GET['greenberry_return'] )
			? esc_url_raw( wp_unslash( $_GET['greenberry_return'] ) )
			: $this->tab_url( $tabs[ $screen->post_type ] );

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
	 * Highlights the Greenberry parent menu on hidden CPT editor screens.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string
	 */
	public function highlight_parent_menu( $parent_file ) {
		$screen = get_current_screen();
		if ( $screen && ! empty( $screen->post_type ) && isset( $this->editor_post_type_tabs()[ $screen->post_type ] ) ) {
			return 'greenberry';
		}

		return $parent_file;
	}

	/**
	 * Highlights the Newsletter submenu on hidden CPT editor screens.
	 *
	 * @param string $submenu_file Current submenu file.
	 * @return string
	 */
	public function highlight_submenu( $submenu_file ) {
		$screen = get_current_screen();
		if ( $screen && ! empty( $screen->post_type ) && isset( $this->editor_post_type_tabs()[ $screen->post_type ] ) ) {
			return 'greenberry-newsletter';
		}

		return $submenu_file;
	}

	/**
	 * Returns the Greenberry tab URL.
	 *
	 * @param string $tab Tab key.
	 * @param array  $extra Extra query args.
	 * @return string
	 */
	private function tab_url( $tab, $extra = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'greenberry-newsletter',
					'tab'  => sanitize_key( $tab ),
				),
				$extra
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Builds an add/edit URL that carries the Greenberry return location.
	 *
	 * @param string $post_type Post type.
	 * @param string $tab Return tab.
	 * @param int    $post_id Optional post ID.
	 * @return string
	 */
	private function editor_url( $post_type, $tab, $post_id = 0 ) {
		$post_id = absint( $post_id );
		$url     = $post_id ? get_edit_post_link( $post_id, 'raw' ) : admin_url( 'post-new.php?post_type=' . sanitize_key( $post_type ) );

		if ( ! $url ) {
			$url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		}

		return add_query_arg( 'greenberry_return', $this->tab_url( $tab ), $url );
	}

	/**
	 * Hidden editor post types and their Greenberry return tabs.
	 *
	 * @return array<string,string>
	 */
	private function editor_post_type_tabs() {
		return array(
			Campaign_Post_Type::POST_TYPE       => 'campaigns',
			Email_Template_Post_Type::POST_TYPE => 'templates',
		);
	}

	/**
	 * Moves a hidden CPT item to the bin and returns to its Greenberry tab.
	 *
	 * @param string $nonce_action Nonce action.
	 * @param string $post_type Expected post type.
	 * @param string $tab Return tab.
	 * @param string $success_notice Success notice key.
	 * @return void
	 */
	private function delete_post_type_item( $nonce_action, $post_type, $tab, $success_notice ) {
		$this->guard_action( $nonce_action );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$post    = get_post( $post_id );

		if ( ! $post || $post_type !== $post->post_type ) {
			$this->redirect( $tab, 'post_not_found' );
		}

		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this item.', 'greenberry' ) );
		}

		$deleted = wp_trash_post( $post_id );

		$this->redirect( $tab, $deleted ? $success_notice : 'post_delete_failed' );
	}

	/**
	 * Verifies permissions and nonce.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private function guard_action( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the Newsletter module.', 'greenberry' ) );
		}

		check_admin_referer( $nonce_action );
	}

	/**
	 * Redirects back to a tab with a notice.
	 *
	 * @param string $tab Tab.
	 * @param string $notice Notice.
	 * @param array  $extra Extra query args.
	 * @return void
	 */
	private function redirect( $tab, $notice, $extra = array() ) {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'page'              => 'greenberry-newsletter',
						'tab'               => sanitize_key( $tab ),
						'greenberry_notice' => sanitize_key( $notice ),
					),
					$extra
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
