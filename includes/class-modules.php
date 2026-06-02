<?php
/**
 * Module registry.
 *
 * @package Greenberry
 */

namespace Greenberry;

defined( 'ABSPATH' ) || exit;

/**
 * Reads module settings and loads enabled modules.
 */
class Modules {
	const OPTION_NAME = 'greenberry_modules';

	/**
	 * Known plugin modules.
	 *
	 * @return array<string,array<string,string|bool>>
	 */
	public function all() {
		return array(
			'newsletter' => array(
				'name'        => __( 'Newsletter', 'greenberry' ),
				'description' => __( 'Collect consented email subscribers, organize lists with tags, and send manual or automated email updates.', 'greenberry' ),
				'default'     => true,
			),
		);
	}

	/**
	 * Ensures the modules option exists with sensible defaults.
	 *
	 * @return void
	 */
	public function ensure_defaults() {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		$defaults = array();
		foreach ( $this->all() as $key => $module ) {
			$defaults[ $key ] = ! empty( $module['default'] );
		}

		add_option( self::OPTION_NAME, $defaults );
	}

	/**
	 * Gets enabled/disabled states.
	 *
	 * @return array<string,bool>
	 */
	public function get_states() {
		$this->ensure_defaults();

		$states = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $states ) ) {
			$states = array();
		}

		foreach ( $this->all() as $key => $module ) {
			if ( ! array_key_exists( $key, $states ) ) {
				$states[ $key ] = ! empty( $module['default'] );
			}
		}

		return array_map( 'boolval', $states );
	}

	/**
	 * Checks if a module is active.
	 *
	 * @param string $module Module key.
	 * @return bool
	 */
	public function is_active( $module ) {
		$states = $this->get_states();

		return ! empty( $states[ $module ] );
	}

	/**
	 * Persists enabled/disabled states.
	 *
	 * @param array<string,bool> $states New module states.
	 * @return void
	 */
	public function save_states( $states ) {
		$clean = array();

		foreach ( $this->all() as $key => $module ) {
			$clean[ $key ] = ! empty( $states[ $key ] );
		}

		update_option( self::OPTION_NAME, $clean );
	}

	/**
	 * Loads currently enabled modules.
	 *
	 * @return void
	 */
	public function load_active_modules() {
		if ( $this->is_active( 'newsletter' ) ) {
			\Greenberry\Newsletter\Module::init();
		}
	}
}
