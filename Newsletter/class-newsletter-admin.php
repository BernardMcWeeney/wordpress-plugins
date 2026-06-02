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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_greenberry_newsletter_add_contact', array( $this, 'add_contact' ) );
		add_action( 'admin_post_greenberry_newsletter_import', array( $this, 'import_contacts' ) );
		add_action( 'admin_post_greenberry_newsletter_export', array( $this, 'export_contacts' ) );
		add_action( 'admin_post_greenberry_newsletter_create_list', array( $this, 'create_list' ) );
		add_action( 'admin_post_greenberry_newsletter_create_campaign', array( $this, 'create_campaign' ) );
		add_action( 'admin_post_greenberry_newsletter_send_campaign', array( $this, 'send_campaign' ) );
		add_action( 'admin_post_greenberry_newsletter_create_automation', array( $this, 'create_automation' ) );
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
	 * Loads admin helpers.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'greenberry-newsletter' ) ) {
			return;
		}

		wp_enqueue_script(
			'greenberry-newsletter-admin',
			GREENBERRY_PLUGIN_URL . 'Newsletter/admin.js',
			array(),
			GREENBERRY_VERSION,
			true
		);

		wp_localize_script(
			'greenberry-newsletter-admin',
			'greenberryNewsletterAdmin',
			array(
				'defaultSubject'   => __( 'Campaign subject', 'greenberry' ),
				'defaultPreheader' => __( 'Preheader text appears here.', 'greenberry' ),
				'defaultContent'   => __( 'Write campaign content to preview the email body.', 'greenberry' ),
			)
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
	 * Creates a manual campaign.
	 *
	 * @return void
	 */
	public function create_campaign() {
		$this->guard_action( 'greenberry_newsletter_create_campaign' );

		$data = array(
			'name'      => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
			'subject'   => isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
			'preheader' => isset( $_POST['preheader'] ) ? wp_unslash( $_POST['preheader'] ) : '',
			'content'   => isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '',
			'list_id'   => isset( $_POST['list_id'] ) ? absint( $_POST['list_id'] ) : 0,
			'type'      => 'manual',
		);

		if ( ! empty( $_POST['greenberry_send_test'] ) ) {
			$result = $this->mailer->send_test_campaign(
				$data,
				isset( $_POST['test_recipient'] ) ? wp_unslash( $_POST['test_recipient'] ) : ''
			);

			$this->redirect( 'campaigns', is_wp_error( $result ) ? $result->get_error_code() : 'campaign_test_sent' );
		}

		$result = $this->repository->create_campaign( $data );

		$this->redirect( 'campaigns', is_wp_error( $result ) ? $result->get_error_code() : 'campaign_created' );
	}

	/**
	 * Sends a campaign.
	 *
	 * @return void
	 */
	public function send_campaign() {
		$this->guard_action( 'greenberry_newsletter_send_campaign' );

		$result = $this->mailer->send_campaign( isset( $_POST['campaign_id'] ) ? absint( $_POST['campaign_id'] ) : 0 );

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
			)
		);

		$this->redirect( 'automations', is_wp_error( $result ) ? $result->get_error_code() : 'automation_created' );
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
		if ( ! in_array( $tab, array( 'contacts', 'lists', 'campaigns', 'automations' ), true ) ) {
			$tab = 'contacts';
		}

		?>
		<div class="wrap greenberry-admin">
			<h1><?php esc_html_e( 'Newsletter', 'greenberry' ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php $this->render_tabs( $tab ); ?>

			<?php
			if ( 'contacts' === $tab ) {
				$this->render_contacts_tab();
			} elseif ( 'lists' === $tab ) {
				$this->render_lists_tab();
			} elseif ( 'campaigns' === $tab ) {
				$this->render_campaigns_tab();
			} elseif ( 'automations' === $tab ) {
				$this->render_automations_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Renders contacts tab.
	 *
	 * @return void
	 */
	private function render_contacts_tab() {
		$contacts = $this->repository->get_contacts( array( 'limit' => 50 ) );
		?>
		<div class="greenberry-grid">
			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'Add Contact', 'greenberry' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="greenberry_newsletter_add_contact">
					<?php wp_nonce_field( 'greenberry_newsletter_add_contact' ); ?>
					<div class="greenberry-field">
						<label for="greenberry-contact-email"><?php esc_html_e( 'Email', 'greenberry' ); ?></label>
						<input id="greenberry-contact-email" type="email" name="email" required>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-contact-first-name"><?php esc_html_e( 'First name', 'greenberry' ); ?></label>
						<input id="greenberry-contact-first-name" type="text" name="first_name">
					</div>
					<div class="greenberry-field">
						<label for="greenberry-contact-last-name"><?php esc_html_e( 'Last name', 'greenberry' ); ?></label>
						<input id="greenberry-contact-last-name" type="text" name="last_name">
					</div>
					<div class="greenberry-field">
						<label for="greenberry-contact-tags"><?php esc_html_e( 'Tags', 'greenberry' ); ?></label>
						<input id="greenberry-contact-tags" type="text" name="tags" value="newsletter">
					</div>
					<label class="greenberry-field">
						<input type="checkbox" name="confirm_consent" value="1" required>
						<?php esc_html_e( 'Consent to store and email this contact has been confirmed.', 'greenberry' ); ?>
					</label>
					<?php submit_button( __( 'Save Contact', 'greenberry' ) ); ?>
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
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $contacts ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No contacts yet.', 'greenberry' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $contacts as $contact ) : ?>
							<tr>
								<td><?php echo esc_html( $contact->email ); ?></td>
								<td><?php echo esc_html( trim( $contact->first_name . ' ' . $contact->last_name ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $contact->status ) ); ?></td>
								<td><?php $this->render_tag_badges( $this->repository->get_contact_tags( $contact->id ), __( 'No tags', 'greenberry' ) ); ?></td>
								<td><?php echo esc_html( $contact->created_at ); ?></td>
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
		$lists = $this->repository->get_lists();
		?>
		<div class="greenberry-grid">
			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'Create List', 'greenberry' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="greenberry_newsletter_create_list">
					<?php wp_nonce_field( 'greenberry_newsletter_create_list' ); ?>
					<div class="greenberry-field">
						<label for="greenberry-list-name"><?php esc_html_e( 'Name', 'greenberry' ); ?></label>
						<input id="greenberry-list-name" type="text" name="name" required>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-list-description"><?php esc_html_e( 'Description', 'greenberry' ); ?></label>
						<textarea id="greenberry-list-description" name="description" rows="3"></textarea>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-list-tags"><?php esc_html_e( 'Tags', 'greenberry' ); ?></label>
						<input id="greenberry-list-tags" type="text" name="tags" placeholder="newsletter, members">
					</div>
					<div class="greenberry-field">
						<label for="greenberry-list-match-mode"><?php esc_html_e( 'Match mode', 'greenberry' ); ?></label>
						<select id="greenberry-list-match-mode" name="match_mode">
							<option value="any"><?php esc_html_e( 'Any listed tag', 'greenberry' ); ?></option>
							<option value="all"><?php esc_html_e( 'All listed tags', 'greenberry' ); ?></option>
						</select>
					</div>
					<?php submit_button( __( 'Create List', 'greenberry' ) ); ?>
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
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $lists ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No lists yet.', 'greenberry' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $lists as $list ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $list->name ); ?></strong></td>
								<td><?php $this->render_tag_badges( $this->repository->get_list_tag_slugs( $list ), __( 'All subscribers', 'greenberry' ) ); ?></td>
								<td><?php echo esc_html( 'all' === $list->match_mode ? __( 'All', 'greenberry' ) : __( 'Any', 'greenberry' ) ); ?></td>
								<td><?php echo esc_html( $this->repository->count_contacts_for_list( $list->id ) ); ?></td>
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
		$campaigns = $this->repository->get_campaigns();
		?>
		<div class="greenberry-grid">
			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'Create Manual Campaign', 'greenberry' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-newsletter-composer" data-greenberry-newsletter-composer>
					<input type="hidden" name="action" value="greenberry_newsletter_create_campaign">
					<?php wp_nonce_field( 'greenberry_newsletter_create_campaign' ); ?>

					<div class="greenberry-newsletter-composer__grid">
						<div>
							<div class="greenberry-field">
								<label for="greenberry-campaign-name"><?php esc_html_e( 'Name', 'greenberry' ); ?></label>
								<input id="greenberry-campaign-name" type="text" name="name" required>
							</div>
							<div class="greenberry-field">
								<label for="greenberry-campaign-list"><?php esc_html_e( 'List', 'greenberry' ); ?></label>
								<?php $this->render_list_select( 'greenberry-campaign-list' ); ?>
							</div>
							<div class="greenberry-field">
								<label for="greenberry-campaign-subject"><?php esc_html_e( 'Subject', 'greenberry' ); ?></label>
								<input id="greenberry-campaign-subject" type="text" name="subject" required data-greenberry-newsletter-subject>
							</div>
							<div class="greenberry-field">
								<label for="greenberry-campaign-preheader"><?php esc_html_e( 'Preheader', 'greenberry' ); ?></label>
								<input id="greenberry-campaign-preheader" type="text" name="preheader" data-greenberry-newsletter-preheader>
							</div>
							<div class="greenberry-field">
								<label for="greenberry-campaign-content"><?php esc_html_e( 'Content', 'greenberry' ); ?></label>
								<textarea id="greenberry-campaign-content" name="content" rows="12" data-greenberry-newsletter-content></textarea>
							</div>
							<div class="greenberry-field">
								<label for="greenberry-campaign-test-recipient"><?php esc_html_e( 'Test recipient', 'greenberry' ); ?></label>
								<input id="greenberry-campaign-test-recipient" type="email" name="test_recipient" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							</div>
							<div class="greenberry-actions">
								<?php submit_button( __( 'Save Campaign', 'greenberry' ), 'primary', 'submit', false ); ?>
								<button type="submit" name="greenberry_send_test" value="1" class="button button-secondary">
									<?php esc_html_e( 'Send Test', 'greenberry' ); ?>
								</button>
							</div>
						</div>

						<aside class="greenberry-newsletter-preview" aria-live="polite">
							<div class="greenberry-newsletter-preview__header">
								<span><?php esc_html_e( 'Inbox preview', 'greenberry' ); ?></span>
								<strong data-greenberry-newsletter-preview-subject><?php esc_html_e( 'Campaign subject', 'greenberry' ); ?></strong>
								<small data-greenberry-newsletter-preview-preheader><?php esc_html_e( 'Preheader text appears here.', 'greenberry' ); ?></small>
							</div>
							<div class="greenberry-newsletter-preview__body" data-greenberry-newsletter-preview-content>
								<p><?php esc_html_e( 'Write campaign content to preview the email body.', 'greenberry' ); ?></p>
							</div>
						</aside>
					</div>
				</form>
			</div>

			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'Campaigns', 'greenberry' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Status', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Sent', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Action', 'greenberry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $campaigns ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No campaigns yet.', 'greenberry' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $campaigns as $campaign ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $campaign->name ); ?></strong></td>
								<td><?php echo esc_html( $campaign->subject ); ?></td>
								<td><?php echo esc_html( ucfirst( $campaign->status ) ); ?></td>
								<td><?php echo esc_html( $campaign->sent_at ); ?></td>
								<td>
									<?php if ( 'sent' !== $campaign->status ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="greenberry_newsletter_send_campaign">
											<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign->id ); ?>">
											<?php wp_nonce_field( 'greenberry_newsletter_send_campaign' ); ?>
											<?php submit_button( __( 'Send Now', 'greenberry' ), 'secondary small', 'submit', false ); ?>
										</form>
									<?php endif; ?>
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
	 * Renders automations tab.
	 *
	 * @return void
	 */
	private function render_automations_tab() {
		$automations = $this->repository->get_automations();
		?>
		<div class="greenberry-grid">
			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'Create Automation', 'greenberry' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="greenberry_newsletter_create_automation">
					<?php wp_nonce_field( 'greenberry_newsletter_create_automation' ); ?>
					<div class="greenberry-field">
						<label for="greenberry-automation-name"><?php esc_html_e( 'Name', 'greenberry' ); ?></label>
						<input id="greenberry-automation-name" type="text" name="name" required>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-automation-trigger"><?php esc_html_e( 'Trigger', 'greenberry' ); ?></label>
						<select id="greenberry-automation-trigger" name="trigger_type">
							<option value="weekly_digest"><?php esc_html_e( 'Weekly digest', 'greenberry' ); ?></option>
							<option value="daily_digest"><?php esc_html_e( 'Daily digest', 'greenberry' ); ?></option>
							<option value="post_publish"><?php esc_html_e( 'When a post is published', 'greenberry' ); ?></option>
						</select>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-automation-list"><?php esc_html_e( 'List', 'greenberry' ); ?></label>
						<?php $this->render_list_select( 'greenberry-automation-list' ); ?>
					</div>
					<div class="greenberry-field">
						<label for="greenberry-automation-post-types"><?php esc_html_e( 'Post types', 'greenberry' ); ?></label>
						<input id="greenberry-automation-post-types" type="text" name="post_types" value="post">
					</div>
					<div class="greenberry-field">
						<label for="greenberry-automation-subject"><?php esc_html_e( 'Subject', 'greenberry' ); ?></label>
						<input id="greenberry-automation-subject" type="text" name="subject" value="<?php echo esc_attr__( '{site_name} updates', 'greenberry' ); ?>" required>
						<p class="description"><?php esc_html_e( 'Use {site_name}; post-publish automations can also use {post_title}.', 'greenberry' ); ?></p>
					</div>
					<?php submit_button( __( 'Create Automation', 'greenberry' ) ); ?>
				</form>
			</div>

			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'Automations', 'greenberry' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Trigger', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Post types', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Last sent', 'greenberry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $automations ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'No automations yet.', 'greenberry' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $automations as $automation ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $automation->name ); ?></strong></td>
								<td><?php echo esc_html( str_replace( '_', ' ', ucfirst( $automation->trigger_type ) ) ); ?></td>
								<td><?php echo esc_html( implode( ', ', $this->repository->get_automation_post_types( $automation ) ) ); ?></td>
								<td><?php echo esc_html( $automation->last_sent_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
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
			'missing_consent'           => __( 'Consent confirmation is required.', 'greenberry' ),
			'missing_csv'               => __( 'Please choose a CSV file.', 'greenberry' ),
			'imported'                  => sprintf(
				/* translators: %d: imported contacts. */
				__( 'Imported %d contacts.', 'greenberry' ),
				isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0
			),
			'list_created'              => __( 'List created.', 'greenberry' ),
			'campaign_created'          => __( 'Campaign saved.', 'greenberry' ),
			'campaign_test_sent'        => __( 'Test campaign sent.', 'greenberry' ),
			'campaign_sent'             => sprintf(
				/* translators: 1: sent count, 2: total count. */
				__( 'Campaign sent to %1$d of %2$d contacts.', 'greenberry' ),
				isset( $_GET['sent'] ) ? absint( $_GET['sent'] ) : 0,
				isset( $_GET['total'] ) ? absint( $_GET['total'] ) : 0
			),
			'automation_created'        => __( 'Automation created.', 'greenberry' ),
			'invalid_email'             => __( 'Please enter a valid email address.', 'greenberry' ),
			'contact_insert_failed'     => __( 'Could not save the contact.', 'greenberry' ),
			'contact_update_failed'     => __( 'Could not update the contact.', 'greenberry' ),
			'missing_list_name'         => __( 'List name is required.', 'greenberry' ),
			'list_insert_failed'        => __( 'Could not create the list. The name may already exist.', 'greenberry' ),
			'missing_campaign_fields'   => __( 'Campaign name and subject are required.', 'greenberry' ),
			'campaign_insert_failed'    => __( 'Could not create the campaign.', 'greenberry' ),
			'invalid_test_recipient'    => __( 'Please enter a valid test recipient email address.', 'greenberry' ),
			'test_send_failed'          => __( 'The test email could not be sent.', 'greenberry' ),
			'missing_automation_fields' => __( 'Automation name, trigger, and subject are required.', 'greenberry' ),
		);

		$message = isset( $messages[ $notice ] ) ? $messages[ $notice ] : __( 'Action complete.', 'greenberry' );
		$type    = false !== strpos( $notice, 'failed' ) || false !== strpos( $notice, 'missing' ) || 0 === strpos( $notice, 'invalid_' ) ? 'error' : 'success';
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
	private function render_list_select( $id ) {
		$lists = $this->repository->get_lists();
		?>
		<select id="<?php echo esc_attr( $id ); ?>" name="list_id">
			<option value="0"><?php esc_html_e( 'All subscribed contacts', 'greenberry' ); ?></option>
			<?php foreach ( $lists as $list ) : ?>
				<option value="<?php echo esc_attr( $list->id ); ?>"><?php echo esc_html( $list->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
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
