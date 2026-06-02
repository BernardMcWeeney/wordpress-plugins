<?php
/**
 * Forms module coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Forms module.
 */
class Module {
	/**
	 * Boots module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$mailer = new Mailer();

		( new Form_Post_Type() )->init();
		( new Rest( $mailer ) )->init();
		( new Blocks() )->init();

		if ( is_admin() ) {
			( new Admin() )->init();
		}
	}

	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		// Forms are stored as block-editor posts; nothing to install.
	}

	/**
	 * Runs deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Submissions are never stored, and saved form definitions remain available.
	}
}
