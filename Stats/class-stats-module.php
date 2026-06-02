<?php
/**
 * Stats module coordinator.
 *
 * @package Greenberry
 */

namespace Greenberry\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the Stats module.
 */
class Module {
	/**
	 * Boots module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$repository = new Repository();

		if ( get_option( Repository::DB_VERSION_OPTION ) !== GREENBERRY_VERSION ) {
			$repository->create_tables();
		}

		( new Tracker( $repository ) )->init();

		if ( is_admin() ) {
			( new Admin( $repository ) )->init();
		}
	}

	/**
	 * Runs activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		( new Repository() )->create_tables();
	}

	/**
	 * Runs deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Aggregate stats are intentionally retained.
	}
}
