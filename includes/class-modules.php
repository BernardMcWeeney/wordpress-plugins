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
			'forms'      => array(
				'name'        => __( 'Forms', 'greenberry' ),
				'description' => __( 'Create GDPR-aware forms that email submissions and attachments without storing responses.', 'greenberry' ),
				'default'     => true,
			),
			'social'     => array(
				'name'        => __( 'Social', 'greenberry' ),
				'description' => __( 'Publish matching content to connected social channels with branded editor previews and per-post controls.', 'greenberry' ),
				'default'     => true,
			),
			'stats'      => array(
				'name'        => __( 'Stats', 'greenberry' ),
				'description' => __( 'Track aggregate post and page views, show counts in content lists, and review daily and weekly top pages.', 'greenberry' ),
				'default'     => true,
			),
			'admin_colours' => array(
				'name'        => __( 'Admin Colours', 'greenberry' ),
				'description' => __( 'Register and apply a WordPress administration color scheme from theme presets or custom colours.', 'greenberry' ),
				'default'     => true,
			),
			'admin_login'   => array(
				'name'        => __( 'Admin Login', 'greenberry' ),
				'description' => __( 'Theme the WordPress login screen with block theme preset colours, a branded message, logo, and optional background image.', 'greenberry' ),
				'default'     => true,
			),
			'category_featured_image' => array(
				'name'        => __( 'Category Featured Image', 'greenberry' ),
				'description' => __( 'Assign a fallback featured image from taxonomy terms, post types, or a global default when posts are saved without one.', 'greenberry' ),
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

		if ( $this->is_active( 'forms' ) ) {
			\Greenberry\Forms\Module::init();
		}

		if ( $this->is_active( 'social' ) ) {
			\Greenberry\Social\Module::init();
		}

		if ( $this->is_active( 'stats' ) ) {
			\Greenberry\Stats\Module::init();
		}

		if ( $this->is_active( 'admin_colours' ) ) {
			\Greenberry\AdminColours\Module::init();
		}

		if ( $this->is_active( 'admin_login' ) ) {
			\Greenberry\AdminLogin\Module::init();
		}

		if ( $this->is_active( 'category_featured_image' ) ) {
			\Greenberry\CategoryFeaturedImage\Module::init();
		}
	}
}
