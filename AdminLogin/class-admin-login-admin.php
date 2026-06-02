<?php
/**
 * Admin Login admin screen.
 *
 * @package Greenberry
 */

namespace Greenberry\AdminLogin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves Admin Login settings.
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_greenberry_admin_login_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Registers Admin Login submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'greenberry',
			__( 'Admin Login', 'greenberry' ),
			__( 'Admin Login', 'greenberry' ),
			'manage_options',
			'greenberry-admin-login',
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueues admin media-picker behavior.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'greenberry_page_greenberry-admin-login' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'greenberry-admin-login-admin',
			GREENBERRY_PLUGIN_URL . 'AdminLogin/admin.js',
			array(),
			GREENBERRY_VERSION,
			true
		);
		wp_localize_script(
			'greenberry-admin-login-admin',
			'greenberryAdminLogin',
			array(
				'chooseTitle' => __( 'Choose an image', 'greenberry' ),
				'chooseText'  => __( 'Use this image', 'greenberry' ),
			)
		);
	}

	/**
	 * Saves settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Admin Login.', 'greenberry' ) );
		}

		check_admin_referer( 'greenberry_admin_login_save_settings' );

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

		$settings       = $this->settings->get();
		$background_id  = $this->settings->get_custom_background_id();
		$logo_id        = $this->settings->get_custom_logo_id();
		$background_url = $this->settings->get_background_url( 'large' );
		$logo_url       = $this->settings->get_logo_url( 'thumbnail' );
		$fallback_logo  = $this->settings->get_site_logo_url( 'thumbnail' );
		$site_initials  = $this->settings->get_site_initials();
		$site_name      = get_bloginfo( 'name' );
		$preview_message = trim( str_replace( '{site_name}', $site_name, $settings['message'] ) );
		?>
		<div class="wrap greenberry-admin greenberry-admin-login-admin">
			<h1><?php esc_html_e( 'Admin Login', 'greenberry' ); ?></h1>
			<?php $this->render_notice(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="greenberry_admin_login_save_settings">
				<?php wp_nonce_field( 'greenberry_admin_login_save_settings' ); ?>

				<div class="greenberry-admin-login-layout">
					<section class="greenberry-panel">
						<div class="greenberry-section-heading">
							<h2><?php esc_html_e( 'Login Theme', 'greenberry' ); ?></h2>
						</div>

						<div class="greenberry-field">
							<label for="greenberry-admin-login-message"><?php esc_html_e( 'Login message', 'greenberry' ); ?></label>
							<textarea id="greenberry-admin-login-message" name="message" rows="4" data-greenberry-admin-login-message-input data-site-name="<?php echo esc_attr( $site_name ); ?>"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Use {site_name} to insert the current site name. Leave blank to hide this message.', 'greenberry' ); ?></p>
						</div>

						<?php
						$this->render_media_field(
							'background_image_id',
							__( 'Background image', 'greenberry' ),
							__( 'Choose a wide image from the media library. It will cover the login screen and crop from the center on small screens.', 'greenberry' ),
							$background_id,
							'background'
						);
						$this->render_media_field(
							'logo_image_id',
							__( 'Login logo', 'greenberry' ),
							__( 'Optional. If blank, Greenberry uses the site logo or site icon when one exists.', 'greenberry' ),
							$logo_id,
							'logo'
						);
						?>
					</section>

					<section class="greenberry-panel">
						<div class="greenberry-section-heading">
							<h2><?php esc_html_e( 'Preview', 'greenberry' ); ?></h2>
						</div>

						<div class="greenberry-admin-login-preview" data-greenberry-admin-login-preview style="<?php echo esc_attr( $this->get_background_style( $background_url ) ); ?>">
							<div class="greenberry-admin-login-preview__card">
								<div class="greenberry-admin-login-preview__logo">
									<img
										src="<?php echo esc_url( $logo_url ); ?>"
										alt=""
										data-greenberry-admin-login-preview-logo
										data-default-src="<?php echo esc_url( $fallback_logo ); ?>"
										<?php echo $logo_url ? '' : 'hidden'; ?>
									>
									<span data-greenberry-admin-login-preview-logo-text <?php echo $logo_url ? 'hidden' : ''; ?>><?php echo esc_html( $site_initials ); ?></span>
								</div>
								<div class="greenberry-admin-login-preview__message" data-greenberry-admin-login-preview-message <?php echo '' === $preview_message ? 'hidden' : ''; ?>>
									<?php echo esc_html( $preview_message ); ?>
								</div>
								<div class="greenberry-admin-login-preview__field">
									<span><?php esc_html_e( 'Username or Email Address', 'greenberry' ); ?></span>
									<i></i>
								</div>
								<div class="greenberry-admin-login-preview__field">
									<span><?php esc_html_e( 'Password', 'greenberry' ); ?></span>
									<i></i>
								</div>
								<div class="greenberry-admin-login-preview__button"><?php esc_html_e( 'Log In', 'greenberry' ); ?></div>
								<div class="greenberry-admin-login-preview__links"><?php esc_html_e( 'Lost your password?', 'greenberry' ); ?></div>
							</div>
						</div>
					</section>
				</div>

				<?php submit_button( __( 'Save Admin Login Settings', 'greenberry' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders one media image field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param string $description Help text.
	 * @param int    $image_id Attachment ID.
	 * @param string $role Preview role.
	 * @return void
	 */
	private function render_media_field( $name, $label, $description, $image_id, $role ) {
		$field_id  = 'greenberry-admin-login-' . str_replace( '_', '-', $name );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="greenberry-field greenberry-media-field" data-greenberry-media-role="<?php echo esc_attr( $role ); ?>">
			<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input id="<?php echo esc_attr( $field_id ); ?>" type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $image_id ); ?>" data-greenberry-media-id>

			<div class="greenberry-media-field__preview <?php echo $image_url ? '' : 'is-empty'; ?>">
				<img src="<?php echo esc_url( $image_url ); ?>" alt="" data-greenberry-media-preview <?php echo $image_url ? '' : 'hidden'; ?>>
				<span data-greenberry-media-placeholder <?php echo $image_url ? 'hidden' : ''; ?>><?php esc_html_e( 'No image selected', 'greenberry' ); ?></span>
			</div>

			<div class="greenberry-actions">
				<button type="button" class="button" data-greenberry-media-choose><?php esc_html_e( 'Choose Image', 'greenberry' ); ?></button>
				<button type="button" class="button" data-greenberry-media-clear <?php disabled( ! $image_id ); ?>><?php esc_html_e( 'Clear', 'greenberry' ); ?></button>
			</div>

			<p class="description"><?php echo esc_html( $description ); ?></p>
		</div>
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

		$notice   = sanitize_key( $_GET['greenberry_notice'] );
		$messages = array(
			'settings_saved' => __( 'Admin Login settings saved.', 'greenberry' ),
		);
		$message  = isset( $messages[ $notice ] ) ? $messages[ $notice ] : __( 'Action complete.', 'greenberry' );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Redirects back to Admin Login settings.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'greenberry-admin-login',
					'greenberry_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Builds a safe inline background style.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	private function get_background_style( $url ) {
		if ( ! $url ) {
			return '';
		}

		return "background-image: url('" . esc_url( $url ) . "');";
	}
}
