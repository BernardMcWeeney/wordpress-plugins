<?php
/**
 * Admin Login module coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry\AdminLogin;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Admin Login module.
 */
class Module {
	/**
	 * Boots module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$settings = new Settings();
		$settings->ensure_defaults();

		( new Login( $settings ) )->init();

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
		// Saved login settings remain available if the module is re-enabled.
	}
}
