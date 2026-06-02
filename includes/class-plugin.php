<?php
/**
 * Main plugin coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates bootstrapping, activation, and module loading.
 */
class Plugin {
	/**
	 * Active module registry.
	 *
	 * @var Modules|null
	 */
	private static $modules = null;

	/**
	 * Loads required classes.
	 *
	 * @return void
	 */
	private static function require_files() {
		require_once GREENBERRY_PLUGIN_DIR . 'includes/class-modules.php';
		require_once GREENBERRY_PLUGIN_DIR . 'includes/class-admin.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Newsletter/class-newsletter-repository.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Newsletter/class-newsletter-email-template.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Newsletter/class-newsletter-mailer.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Newsletter/class-newsletter-rest.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Newsletter/class-newsletter-blocks.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Newsletter/class-newsletter-admin.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Newsletter/class-newsletter-module.php';
	}

	/**
	 * Boots the plugin.
	 *
	 * @return void
	 */
	public static function init() {
		self::require_files();

		self::$modules = new Modules();
		self::$modules->load_active_modules();

		if ( is_admin() ) {
			( new Admin( self::$modules ) )->init();
		}
	}

	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		self::require_files();

		$modules = new Modules();
		$modules->ensure_defaults();

		\Greenberry\Newsletter\Module::activate();
	}

	/**
	 * Runs deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::require_files();

		\Greenberry\Newsletter\Module::deactivate();
	}
}
