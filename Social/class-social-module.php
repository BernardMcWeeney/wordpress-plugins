<?php
/**
 * Social module coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry\Social;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Social module.
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

		$publisher = new Publisher( $settings );
		add_action( 'transition_post_status', array( $publisher, 'handle_post_transition' ), 10, 3 );
		add_action( 'greenberry_social_publish_post', array( $publisher, 'publish_scheduled_post' ), 10, 1 );

		if ( is_admin() ) {
			( new Admin( $settings, $publisher ) )->init();
			( new Editor( $settings ) )->init();
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
		// Settings and logs are intentionally retained.
	}
}
