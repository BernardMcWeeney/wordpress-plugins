<?php
/**
 * Admin Colours admin screen.
 *
 * @package Greenberry
 */

namespace Greenberry\AdminColours;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles Admin Colours settings.
 */
class Admin {
	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings store.
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
		add_action( 'admin_post_greenberry_admin_colours_save', array( $this, 'save' ) );
		add_action( 'admin_post_greenberry_admin_colours_reset', array( $this, 'reset' ) );
	}

	/**
	 * Registers Admin Colours submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'greenberry',
			__( 'Admin Colours', 'greenberry' ),
			__( 'Admin Colours', 'greenberry' ),
			'manage_options',
			'greenberry-admin-colours',
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
		if ( false === strpos( $hook, 'greenberry-admin-colours' ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'greenberry-admin-colours-admin',
			GREENBERRY_PLUGIN_URL . 'AdminColours/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			GREENBERRY_VERSION,
			true
		);
	}

	/**
	 * Saves Admin Colours settings.
	 *
	 * @return void
	 */
	public function save() {
		$this->guard_action( 'greenberry_admin_colours_save' );

		$data = isset( $_POST['greenberry_admin_colours'] ) && is_array( $_POST['greenberry_admin_colours'] )
			? wp_unslash( $_POST['greenberry_admin_colours'] )
			: array();

		$this->settings->save( $data );
		$this->redirect( 'settings_saved' );
	}

	/**
	 * Resets Admin Colours settings to defaults.
	 *
	 * @return void
	 */
	public function reset() {
		$this->guard_action( 'greenberry_admin_colours_reset' );
		$this->settings->reset();
		$this->redirect( 'settings_reset' );
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings      = $this->settings->get();
		$colours       = $this->settings->get_colours();
		$preset_tokens = $this->settings->get_preset_tokens();
		$fields        = $this->settings->get_custom_colour_fields();
		\Greenberry\Admin_UI::open(
			__( 'Admin Colours', 'greenberry' ),
			__( 'Apply a WordPress admin colour scheme from your theme palette or your own custom colours.', 'greenberry' )
		);
		$this->render_notice();
		?>
			<div class="greenberry-grid greenberry-grid--admin-colours">
				<div class="greenberry-panel">
					<h2><?php esc_html_e( 'Active Scheme', 'greenberry' ); ?></h2>
					<p class="greenberry-muted">
						<?php esc_html_e( 'Greenberry Admin Colours is registered as a WordPress administration color scheme and applied while this module is enabled.', 'greenberry' ); ?>
					</p>
					<div class="greenberry-admin-colour-preview" style="<?php echo esc_attr( $this->preview_style( $colours ) ); ?>">
						<div class="greenberry-admin-colour-preview__bar"></div>
						<div class="greenberry-admin-colour-preview__body">
							<span></span>
							<span></span>
							<span></span>
						</div>
					</div>
				</div>

				<div class="greenberry-panel">
					<h2><?php esc_html_e( 'Colour Source', 'greenberry' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="greenberry_admin_colours_save">
						<?php wp_nonce_field( 'greenberry_admin_colours_save' ); ?>

						<fieldset class="greenberry-field greenberry-source-cards" data-greenberry-colour-source>
							<legend class="screen-reader-text"><?php esc_html_e( 'Colour source', 'greenberry' ); ?></legend>
							<label class="greenberry-source-card">
								<input type="radio" name="greenberry_admin_colours[source]" value="<?php echo esc_attr( Settings::SOURCE_THEME ); ?>" <?php checked( $settings['source'], Settings::SOURCE_THEME ); ?>>
								<span>
									<strong><?php esc_html_e( 'Theme presets', 'greenberry' ); ?></strong>
									<small><?php esc_html_e( 'Follow the active block theme palette automatically.', 'greenberry' ); ?></small>
								</span>
							</label>
							<label class="greenberry-source-card">
								<input type="radio" name="greenberry_admin_colours[source]" value="<?php echo esc_attr( Settings::SOURCE_CUSTOM ); ?>" <?php checked( $settings['source'], Settings::SOURCE_CUSTOM ); ?>>
								<span>
									<strong><?php esc_html_e( 'Custom colours', 'greenberry' ); ?></strong>
									<small><?php esc_html_e( 'Override the generated admin scheme with explicit colours.', 'greenberry' ); ?></small>
								</span>
							</label>
						</fieldset>

						<div class="greenberry-section" data-greenberry-custom-colours>
							<h3><?php esc_html_e( 'Custom Colours', 'greenberry' ); ?></h3>
							<div class="greenberry-colour-fields">
								<?php foreach ( $fields as $key => $field ) : ?>
									<div class="greenberry-field">
										<label for="greenberry-admin-colour-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
										<input
											id="greenberry-admin-colour-<?php echo esc_attr( $key ); ?>"
											class="greenberry-color-picker"
											type="text"
											name="greenberry_admin_colours[custom_colours][<?php echo esc_attr( $key ); ?>]"
											value="<?php echo esc_attr( $settings['custom_colours'][ $key ] ); ?>"
										>
										<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<?php submit_button( __( 'Save Changes', 'greenberry' ) ); ?>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-danger-zone">
						<input type="hidden" name="action" value="greenberry_admin_colours_reset">
						<?php wp_nonce_field( 'greenberry_admin_colours_reset' ); ?>
						<?php submit_button( __( 'Reset to Theme Presets', 'greenberry' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<div class="greenberry-panel">
				<h2><?php esc_html_e( 'WordPress Preset Defaults', 'greenberry' ); ?></h2>
				<div class="greenberry-token-grid">
					<?php foreach ( $preset_tokens as $token ) : ?>
						<div class="greenberry-token-card">
							<span class="greenberry-colour-swatch" style="background-color: <?php echo esc_attr( $token['colour'] ); ?>"></span>
							<strong><code><?php echo esc_html( $token['variable'] ); ?></code></strong>
							<small>
								<?php
								printf(
									/* translators: 1: resolved colour, 2: fallback colour. */
									esc_html__( 'Resolved: %1$s. Fallback: %2$s.', 'greenberry' ),
									esc_html( $token['colour'] ),
									esc_html( $token['fallback'] )
								);
								?>
							</small>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php
		\Greenberry\Admin_UI::close();
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
			'settings_saved' => __( 'Admin Colours settings saved.', 'greenberry' ),
			'settings_reset' => __( 'Admin Colours reset to the WordPress theme presets.', 'greenberry' ),
		);

		if ( empty( $messages[ $notice ] ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
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
			wp_die( esc_html__( 'You do not have permission to manage Greenberry admin colours.', 'greenberry' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirects back to the settings screen.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function redirect( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'greenberry-admin-colours',
					'greenberry_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Builds inline preview CSS from sanitized colours.
	 *
	 * @param array $colours Active colours.
	 * @return string
	 */
	private function preview_style( $colours ) {
		return sprintf(
			'--preview-menu:%1$s;--preview-menu-text:%2$s;--preview-accent:%3$s;--preview-bg:%4$s;',
			esc_attr( $colours['menu_background'] ),
			esc_attr( $colours['menu_text'] ),
			esc_attr( $colours['accent'] ),
			esc_attr( $colours['background'] )
		);
	}
}
