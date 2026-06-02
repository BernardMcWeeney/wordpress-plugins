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
			'dashicons-admin-generic',
			58
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
		?>
		<div class="wrap greenberry-admin">
			<h1><?php esc_html_e( 'Greenberry Modules', 'greenberry' ); ?></h1>

			<?php if ( isset( $_GET['greenberry_notice'] ) && 'modules_saved' === $_GET['greenberry_notice'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Module settings saved.', 'greenberry' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="greenberry-panel">
				<input type="hidden" name="action" value="greenberry_save_modules">
				<?php wp_nonce_field( 'greenberry_save_modules' ); ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Module', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Status', 'greenberry' ); ?></th>
							<th><?php esc_html_e( 'Purpose', 'greenberry' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $this->modules->all() as $key => $module ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $module['name'] ); ?></strong></td>
								<td>
									<label>
										<input type="checkbox" name="modules[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $states[ $key ] ) ); ?>>
										<?php esc_html_e( 'Enabled', 'greenberry' ); ?>
									</label>
								</td>
								<td><?php echo esc_html( $module['description'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Modules', 'greenberry' ) ); ?>
			</form>
		</div>
		<?php
	}
}
