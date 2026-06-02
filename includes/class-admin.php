<?php
/**
 * Core admin screen.
 *
 * @package Greenberry
 */

namespace Greenberry;

defined( 'ABSPATH' ) || exit;

/**
 * Renders module-level plugin settings.
 */
class Admin {
	/**
	 * Module registry.
	 *
	 * @var Modules
	 */
	private $modules;

	/**
	 * Constructor.
	 *
	 * @param Modules $modules Module registry.
	 */
	public function __construct( Modules $modules ) {
		$this->modules = $modules;
	}

	/**
	 * Hooks admin actions.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_greenberry_save_modules', array( $this, 'save_modules' ) );
	}

	/**
	 * Registers the parent menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Greenberry', 'greenberry' ),
			__( 'Greenberry', 'greenberry' ),
			'manage_options',
			'greenberry',
			array( $this, 'render_modules_page' ),
			'dashicons-screenoptions',
			58
		);

		// Rename the auto-generated first submenu item from "Greenberry" to "Dashboard".
		add_submenu_page(
			'greenberry',
			__( 'Dashboard', 'greenberry' ),
			__( 'Dashboard', 'greenberry' ),
			'manage_options',
			'greenberry',
			array( $this, 'render_modules_page' )
		);
	}

	/**
	 * Maps a module key to its settings page slug.
	 *
	 * @return array<string,string>
	 */
	private function settings_slugs() {
		return array(
			'newsletter'              => 'greenberry-newsletter',
			'forms'                   => 'greenberry-forms',
			'social'                  => 'greenberry-social',
			'stats'                   => 'greenberry-stats',
			'admin_colours'           => 'greenberry-admin-colours',
			'admin_login'             => 'greenberry-admin-login',
			'category_featured_image' => 'greenberry-category-featured-image',
		);
	}

	/**
	 * Loads admin CSS where needed.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'greenberry' ) ) {
			return;
		}

		wp_enqueue_style(
			'greenberry-admin',
			GREENBERRY_PLUGIN_URL . 'assets/admin.css',
			array(),
			GREENBERRY_VERSION
		);

		wp_enqueue_script(
			'greenberry-admin',
			GREENBERRY_PLUGIN_URL . 'assets/admin.js',
			array(),
			GREENBERRY_VERSION,
			true
		);
	}

	/**
	 * Saves module states.
	 *
	 * @return void
	 */
	public function save_modules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Greenberry.', 'greenberry' ) );
		}

		check_admin_referer( 'greenberry_save_modules' );

		$posted = isset( $_POST['modules'] ) && is_array( $_POST['modules'] ) ? wp_unslash( $_POST['modules'] ) : array();
		$states = array();

		foreach ( $this->modules->all() as $key => $module ) {
			$states[ $key ] = isset( $posted[ $key ] );
		}

		$this->modules->save_states( $states );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'greenberry',
					'greenberry_notice' => 'modules_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renders module toggles.
	 *
	 * @return void
	 */
	public function render_modules_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$states = $this->modules->get_states();
		$slugs  = $this->settings_slugs();

		Admin_UI::open(
			__( 'Dashboard', 'greenberry' ),
			__( 'Turn modules on or off, then open a module to configure it.', 'greenberry' )
		);

		if ( isset( $_GET['greenberry_notice'] ) && 'modules_saved' === $_GET['greenberry_notice'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Module settings saved.', 'greenberry' ); ?></p>
			</div>
			<?php
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="greenberry_save_modules">
			<?php wp_nonce_field( 'greenberry_save_modules' ); ?>

			<div class="greenberry-modules">
				<?php foreach ( $this->modules->all() as $key => $module ) : ?>
					<?php
					$is_on        = ! empty( $states[ $key ] );
					$settings_url = $is_on && isset( $slugs[ $key ] )
						? add_query_arg( array( 'page' => $slugs[ $key ] ), admin_url( 'admin.php' ) )
						: '';
					?>
					<article class="greenberry-module-card <?php echo esc_attr( $is_on ? 'is-on' : 'is-off' ); ?>">
						<div class="greenberry-module-card__head">
							<h2><?php echo esc_html( $module['name'] ); ?></h2>
							<?php
							Admin_UI::toggle(
								array(
									'name'    => 'modules[' . $key . ']',
									'checked' => $is_on,
									'label'   => $module['name'],
									'compact' => true,
								)
							);
							?>
						</div>
						<p class="greenberry-module-card__desc"><?php echo esc_html( $module['description'] ); ?></p>
						<div class="greenberry-module-card__foot">
							<span class="greenberry-pill <?php echo esc_attr( $is_on ? 'is-on' : '' ); ?>">
								<?php echo esc_html( $is_on ? __( 'Enabled', 'greenberry' ) : __( 'Disabled', 'greenberry' ) ); ?>
							</span>
							<?php if ( $settings_url ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( $settings_url ); ?>">
									<?php esc_html_e( 'Settings', 'greenberry' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>

			<p class="greenberry-modules__save">
				<?php submit_button( __( 'Save Changes', 'greenberry' ), 'primary', 'submit', false ); ?>
			</p>
		</form>
		<?php
		Admin_UI::close();
	}
}
