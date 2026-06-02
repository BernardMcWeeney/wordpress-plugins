<?php
/**
 * Social admin screens.
 *
 * @package Greenberry
 */

namespace Greenberry\Social;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves Social module settings.
 */
class Admin {
	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_post_greenberry_social_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Registers Social submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'greenberry',
			__( 'Social', 'greenberry' ),
			__( 'Social', 'greenberry' ),
			'manage_options',
			'greenberry-social',
			array( $this, 'render' )
		);
	}

	/**
	 * Saves settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Greenberry Social.', 'greenberry' ) );
		}

		check_admin_referer( 'greenberry_social_save_settings' );

		$this->settings->save( wp_unslash( $_POST ) );
		$this->redirect( 'settings_saved' );
	}

	/**
	 * Renders settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = $this->settings->get();
		$providers  = $this->settings->providers();
		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
			)
		);
		$tags       = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
			)
		);
		\Greenberry\Admin_UI::open(
			__( 'Social', 'greenberry' ),
			__( 'Publish matching content to connected social channels with branded post copy and per-post controls.', 'greenberry' ),
			'greenberry-social-admin'
		);
		$this->render_notice();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="greenberry_social_save_settings">
			<?php wp_nonce_field( 'greenberry_social_save_settings' ); ?>

			<div data-greenberry-tabs>
				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Social sections', 'greenberry' ); ?>">
					<button type="button" class="nav-tab nav-tab-active" data-greenberry-tab="publishing"><?php esc_html_e( 'Publishing', 'greenberry' ); ?></button>
					<button type="button" class="nav-tab" data-greenberry-tab="connections"><?php esc_html_e( 'Connections', 'greenberry' ); ?></button>
					<button type="button" class="nav-tab" data-greenberry-tab="rules"><?php esc_html_e( 'Rules', 'greenberry' ); ?></button>
					<button type="button" class="nav-tab" data-greenberry-tab="activity"><?php esc_html_e( 'Activity', 'greenberry' ); ?></button>
				</nav>

				<div class="greenberry-tab-panel" data-greenberry-panel="publishing">
					<section class="greenberry-panel">
						<h2><?php esc_html_e( 'Publishing', 'greenberry' ); ?></h2>

						<?php
						\Greenberry\Admin_UI::toggle(
							array(
								'name'    => 'enabled',
								'checked' => ! empty( $settings['enabled'] ),
								'label'   => __( 'Enable automatic social publishing', 'greenberry' ),
								'help'    => __( 'Newly published matching posts are sent once, in the background.', 'greenberry' ),
							)
						);
						?>

						<div class="greenberry-field">
							<label for="greenberry-social-template"><?php esc_html_e( 'Message template', 'greenberry' ); ?></label>
							<textarea id="greenberry-social-template" name="message_template" rows="5"><?php echo esc_textarea( $settings['message_template'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Tokens: {site_name}, {post_title}, {post_url}, {excerpt}, {author}, {date}, {hashtags}.', 'greenberry' ); ?></p>
						</div>

						<div class="greenberry-field">
							<label><?php esc_html_e( 'Default channels', 'greenberry' ); ?></label>
							<div class="greenberry-checkbox-grid greenberry-checkbox-grid--compact">
								<?php foreach ( $providers as $key => $provider ) : ?>
									<label>
										<input type="checkbox" name="default_channels[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings['default_channels'][ $key ] ) ); ?>>
										<?php echo esc_html( $provider['label'] ); ?>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</section>
				</div>

				<div class="greenberry-tab-panel" data-greenberry-panel="connections" hidden>
					<section class="greenberry-panel">
						<h2><?php esc_html_e( 'Connections', 'greenberry' ); ?></h2>
						<div class="greenberry-social-provider-grid">
							<?php $this->render_bluesky_card( $settings ); ?>
							<?php $this->render_linkedin_card( $settings ); ?>
						</div>
					</section>
				</div>

				<div class="greenberry-tab-panel" data-greenberry-panel="rules" hidden>
					<section class="greenberry-panel">
						<h2><?php esc_html_e( 'Rules', 'greenberry' ); ?></h2>
						<p class="greenberry-muted"><?php esc_html_e( 'A post is published automatically when it matches the selected post types, and any selected categories or tags.', 'greenberry' ); ?></p>
						<div class="greenberry-social-rules">
							<div class="greenberry-field">
								<label><?php esc_html_e( 'Post types', 'greenberry' ); ?></label>
								<div class="greenberry-checkbox-grid">
									<?php foreach ( $this->settings->get_publishable_post_types() as $post_type => $object ) : ?>
										<label>
											<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( in_array( $post_type, $settings['rules']['post_types'], true ) ); ?>>
											<?php echo esc_html( $object->labels->singular_name ); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="greenberry-field">
								<label><?php esc_html_e( 'Categories', 'greenberry' ); ?></label>
								<?php $this->render_term_checklist( $categories, 'categories', $settings['rules']['categories'], __( 'No categories found.', 'greenberry' ) ); ?>
							</div>

							<div class="greenberry-field">
								<label><?php esc_html_e( 'Tags', 'greenberry' ); ?></label>
								<?php $this->render_term_checklist( $tags, 'tags', $settings['rules']['tags'], __( 'No tags found.', 'greenberry' ) ); ?>
							</div>
						</div>
					</section>
				</div>

				<div class="greenberry-tab-panel" data-greenberry-panel="activity" hidden>
					<section class="greenberry-panel">
						<h2><?php esc_html_e( 'Activity', 'greenberry' ); ?></h2>
						<?php $this->render_log_table(); ?>
					</section>
				</div>
			</div>

			<?php submit_button( __( 'Save Changes', 'greenberry' ) ); ?>
		</form>
		<?php
		\Greenberry\Admin_UI::close();
	}

	/**
	 * Renders Bluesky provider card.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private function render_bluesky_card( $settings ) {
		$config = $settings['providers']['bluesky'];
		$status = $this->settings->get_provider_status( 'bluesky', $settings );
		?>
		<div class="greenberry-social-provider">
			<div class="greenberry-social-provider__header">
				<div>
					<h3><?php esc_html_e( 'Bluesky', 'greenberry' ); ?></h3>
					<span class="greenberry-status <?php echo esc_attr( $status['ready'] ? 'is-ready' : '' ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
				</div>
			</div>

			<?php
			\Greenberry\Admin_UI::toggle(
				array(
					'name'    => 'providers[bluesky][enabled]',
					'checked' => ! empty( $config['enabled'] ),
					'label'   => __( 'Enable Bluesky', 'greenberry' ),
				)
			);
			?>

			<div class="greenberry-field">
				<label for="greenberry-bluesky-identifier"><?php esc_html_e( 'Handle or DID', 'greenberry' ); ?></label>
				<input id="greenberry-bluesky-identifier" type="text" name="providers[bluesky][identifier]" value="<?php echo esc_attr( $config['identifier'] ); ?>" placeholder="example.bsky.social">
			</div>

			<div class="greenberry-field">
				<label for="greenberry-bluesky-token"><?php esc_html_e( 'App password', 'greenberry' ); ?></label>
				<input id="greenberry-bluesky-token" type="password" name="providers[bluesky][token]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $config['token'] ) ? __( 'Saved', 'greenberry' ) : '' ); ?>">
				<?php $this->render_clear_token_checkbox( 'bluesky', $config ); ?>
			</div>

			<div class="greenberry-field">
				<label for="greenberry-bluesky-pds"><?php esc_html_e( 'PDS host', 'greenberry' ); ?></label>
				<input id="greenberry-bluesky-pds" type="text" name="providers[bluesky][pds_host]" value="<?php echo esc_attr( $config['pds_host'] ); ?>">
			</div>
		</div>
		<?php
	}

	/**
	 * Renders LinkedIn provider card.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private function render_linkedin_card( $settings ) {
		$config = $settings['providers']['linkedin'];
		$status = $this->settings->get_provider_status( 'linkedin', $settings );
		?>
		<div class="greenberry-social-provider">
			<div class="greenberry-social-provider__header">
				<div>
					<h3><?php esc_html_e( 'LinkedIn', 'greenberry' ); ?></h3>
					<span class="greenberry-status <?php echo esc_attr( $status['ready'] ? 'is-ready' : '' ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
				</div>
			</div>

			<?php
			\Greenberry\Admin_UI::toggle(
				array(
					'name'    => 'providers[linkedin][enabled]',
					'checked' => ! empty( $config['enabled'] ),
					'label'   => __( 'Enable LinkedIn', 'greenberry' ),
				)
			);
			?>

			<div class="greenberry-field">
				<label for="greenberry-linkedin-token"><?php esc_html_e( 'Access token', 'greenberry' ); ?></label>
				<input id="greenberry-linkedin-token" type="password" name="providers[linkedin][token]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $config['token'] ) ? __( 'Saved', 'greenberry' ) : '' ); ?>">
				<?php $this->render_clear_token_checkbox( 'linkedin', $config ); ?>
			</div>

			<div class="greenberry-field">
				<label for="greenberry-linkedin-author"><?php esc_html_e( 'Author URN', 'greenberry' ); ?></label>
				<input id="greenberry-linkedin-author" type="text" name="providers[linkedin][author_urn]" value="<?php echo esc_attr( $config['author_urn'] ); ?>" placeholder="urn:li:organization:123456">
			</div>

			<div class="greenberry-field">
				<label for="greenberry-linkedin-version"><?php esc_html_e( 'API version', 'greenberry' ); ?></label>
				<input id="greenberry-linkedin-version" type="text" name="providers[linkedin][version]" value="<?php echo esc_attr( $config['version'] ); ?>" inputmode="numeric">
			</div>
		</div>
		<?php
	}

	/**
	 * Renders token clear checkbox.
	 *
	 * @param string $provider Provider key.
	 * @param array  $config Provider config.
	 * @return void
	 */
	private function render_clear_token_checkbox( $provider, $config ) {
		if ( empty( $config['token'] ) ) {
			return;
		}
		?>
		<label class="greenberry-inline-check">
			<input type="checkbox" name="clear_token[<?php echo esc_attr( $provider ); ?>]" value="1">
			<?php esc_html_e( 'Clear saved token', 'greenberry' ); ?>
		</label>
		<?php
	}

	/**
	 * Renders a term checklist.
	 *
	 * @param array|\WP_Error $terms Terms.
	 * @param string          $name Field name.
	 * @param array           $selected Selected term IDs.
	 * @param string          $empty_label Empty label.
	 * @return void
	 */
	private function render_term_checklist( $terms, $name, $selected, $empty_label ) {
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			echo '<p class="greenberry-muted">' . esc_html( $empty_label ) . '</p>';
			return;
		}
		?>
		<div class="greenberry-checkbox-grid greenberry-checkbox-grid--scroll">
			<?php foreach ( $terms as $term ) : ?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( absint( $term->term_id ), $selected, true ) ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renders recent activity.
	 *
	 * @return void
	 */
	private function render_log_table() {
		$entries = $this->settings->get_log_entries();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'greenberry' ); ?></th>
					<th><?php esc_html_e( 'Post', 'greenberry' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'greenberry' ); ?></th>
					<th><?php esc_html_e( 'Status', 'greenberry' ); ?></th>
					<th><?php esc_html_e( 'Result', 'greenberry' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $entries ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No Social activity yet.', 'greenberry' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $entry['time'] ) ? $entry['time'] : '' ); ?></td>
						<td>
							<?php if ( ! empty( $entry['post_id'] ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( absint( $entry['post_id'] ) ) ); ?>"><?php echo esc_html( isset( $entry['post_title'] ) ? $entry['post_title'] : __( 'Post', 'greenberry' ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( isset( $entry['post_title'] ) ? $entry['post_title'] : '' ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( isset( $entry['provider'] ) ? $entry['provider'] : '' ); ?></td>
						<td><span class="greenberry-status <?php echo esc_attr( isset( $entry['status'] ) && 'success' === $entry['status'] ? 'is-ready' : 'is-error' ); ?>"><?php echo esc_html( isset( $entry['status'] ) ? ucfirst( $entry['status'] ) : '' ); ?></span></td>
						<td>
							<?php if ( ! empty( $entry['url'] ) ) : ?>
								<a href="<?php echo esc_url( $entry['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View post', 'greenberry' ); ?></a>
							<?php else : ?>
								<?php echo esc_html( isset( $entry['message'] ) ? $entry['message'] : '' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
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
			'settings_saved' => __( 'Social settings saved.', 'greenberry' ),
		);
		$message = isset( $messages[ $notice ] ) ? $messages[ $notice ] : __( 'Action complete.', 'greenberry' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Redirects back to Social settings.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'greenberry-social',
					'greenberry_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
