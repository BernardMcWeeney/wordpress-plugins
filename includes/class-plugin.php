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
		require_once GREENBERRY_PLUGIN_DIR . 'Forms/class-forms-repository.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Forms/class-forms-mailer.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Forms/class-forms-rest.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Forms/class-forms-blocks.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Forms/class-forms-admin.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Forms/class-forms-module.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Social/class-social-settings.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Social/class-social-publisher.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Social/class-social-editor.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Social/class-social-admin.php';
		require_once GREENBERRY_PLUGIN_DIR . 'Social/class-social-module.php';
		require_once GREENBERRY_PLUGIN_DIR . 'AdminColours/class-admin-colours-settings.php';
		require_once GREENBERRY_PLUGIN_DIR . 'AdminColours/class-admin-colours-admin.php';
		require_once GREENBERRY_PLUGIN_DIR . 'AdminColours/class-admin-colours-module.php';
		require_once GREENBERRY_PLUGIN_DIR . 'AdminLogin/class-admin-login-settings.php';
		require_once GREENBERRY_PLUGIN_DIR . 'AdminLogin/class-admin-login-login.php';
		require_once GREENBERRY_PLUGIN_DIR . 'AdminLogin/class-admin-login-admin.php';
		require_once GREENBERRY_PLUGIN_DIR . 'AdminLogin/class-admin-login-module.php';
		require_once GREENBERRY_PLUGIN_DIR . 'CategoryFeaturedImage/class-category-featured-image-settings.php';
		require_once GREENBERRY_PLUGIN_DIR . 'CategoryFeaturedImage/class-category-featured-image-assigner.php';
		require_once GREENBERRY_PLUGIN_DIR . 'CategoryFeaturedImage/class-category-featured-image-admin.php';
		require_once GREENBERRY_PLUGIN_DIR . 'CategoryFeaturedImage/class-category-featured-image-module.php';
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
		\Greenberry\Forms\Module::activate();
		\Greenberry\Social\Module::activate();
		\Greenberry\AdminColours\Module::activate();
		\Greenberry\AdminLogin\Module::activate();
		\Greenberry\CategoryFeaturedImage\Module::activate();
	}

	/**
	 * Runs deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::require_files();

		\Greenberry\Newsletter\Module::deactivate();
		\Greenberry\Forms\Module::deactivate();
		\Greenberry\Social\Module::deactivate();
		\Greenberry\AdminColours\Module::deactivate();
		\Greenberry\AdminLogin\Module::deactivate();
		\Greenberry\CategoryFeaturedImage\Module::deactivate();
	}
}
