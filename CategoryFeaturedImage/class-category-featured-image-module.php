<?php
/**
 * Category Featured Image module coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry\CategoryFeaturedImage;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Category Featured Image module.
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

		( new Assigner( $settings ) )->init();

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
		// Saved defaults are retained so they are available if the module is re-enabled.
	}
}
