<?php
/**
 * Admin Colours module coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry\AdminColours;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Admin Colours module.
 */
class Module {
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
	 * Boots module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$settings = new Settings();
		$settings->ensure_defaults();

		$module = new self( $settings );
		$module->register_hooks();

		if ( is_admin() ) {
			( new Admin( $settings ) )->init();
		}
	}

	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		( new Settings() )->ensure_defaults();
	}

	/**
	 * Runs deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// The saved colours remain available if the module is re-enabled.
	}

	/**
	 * Registers module hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_admin_colour_scheme' ) );
		add_action( 'admin_head', array( $this, 'print_inline_css' ), 20 );
		add_filter( 'get_user_option_admin_color', array( $this, 'force_admin_colour_scheme' ), 10, 3 );
	}

	/**
	 * Registers Greenberry as a WordPress administration color scheme.
	 *
	 * @return void
	 */
	public function register_admin_colour_scheme() {
		wp_admin_css_color(
			Settings::SCHEME_KEY,
			__( 'Greenberry Admin Colours', 'greenberry' ),
			GREENBERRY_PLUGIN_URL . 'AdminColours/admin-colours.css',
			$this->settings->get_admin_scheme_swatches(),
			$this->settings->get_icon_colours()
		);
	}

	/**
	 * Applies the Greenberry admin color scheme while the module is enabled.
	 *
	 * @param string   $result Saved user option.
	 * @param string   $option User option name.
	 * @param \WP_User $user User object.
	 * @return string
	 */
	public function force_admin_colour_scheme( $result, $option = '', $user = null ) {
		return Settings::SCHEME_KEY;
	}

	/**
	 * Prints dynamic CSS custom properties for the active colour source.
	 *
	 * @return void
	 */
	public function print_inline_css() {
		echo '<style id="greenberry-admin-colours-inline">' . "\n";
		echo $this->settings->get_inline_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is built from sanitized hex values and fixed preset tokens.
		echo '</style>' . "\n";
	}
}
