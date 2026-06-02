<?php
/**
 * Admin Login module settings.
 *
 * @package Greenberry
 */

namespace Greenberry\AdminLogin;

defined( 'ABSPATH' ) || exit;

/**
 * Stores themed login settings.
 */
class Settings {
	const OPTION_NAME = 'greenberry_admin_login_settings';

	/**
	 * Ensures the settings option exists.
	 *
	 * @return void
	 */
	public function ensure_defaults() {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		add_option( self::OPTION_NAME, $this->defaults(), '', false );
	}

	/**
	 * Gets normalized settings.
	 *
	 * @return array
	 */
	public function get() {
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return $this->deep_merge( $this->defaults(), $settings );
	}

	/**
	 * Saves settings from admin form data.
	 *
	 * @param array $data Raw form data.
	 * @return void
	 */
	public function save( $data ) {
		$clean = array(
			'message'             => isset( $data['message'] ) ? sanitize_textarea_field( $data['message'] ) : '',
			'background_image_id' => $this->sanitize_image_id( isset( $data['background_image_id'] ) ? $data['background_image_id'] : 0 ),
			'logo_image_id'       => $this->sanitize_image_id( isset( $data['logo_image_id'] ) ? $data['logo_image_id'] : 0 ),
		);

		update_option( self::OPTION_NAME, $clean, false );
	}

	/**
	 * Gets the configured login message with site tokens replaced.
	 *
	 * @return string
	 */
	public function get_message() {
		$settings = $this->get();
		$message  = isset( $settings['message'] ) ? (string) $settings['message'] : '';

		return str_replace( '{site_name}', get_bloginfo( 'name' ), $message );
	}

	/**
	 * Gets the configured background image URL.
	 *
	 * @param string $size Attachment image size.
	 * @return string
	 */
	public function get_background_url( $size = 'full' ) {
		$settings = $this->get();
		$image_id = isset( $settings['background_image_id'] ) ? absint( $settings['background_image_id'] ) : 0;

		return $this->get_attachment_url( $image_id, $size );
	}

	/**
	 * Gets the configured or site-derived logo URL.
	 *
	 * @param string $size Attachment image size.
	 * @return string
	 */
	public function get_logo_url( $size = 'thumbnail' ) {
		$settings = $this->get();
		$image_id = isset( $settings['logo_image_id'] ) ? absint( $settings['logo_image_id'] ) : 0;
		$logo_url = $this->get_attachment_url( $image_id, $size );

		if ( $logo_url ) {
			return $logo_url;
		}

		return $this->get_site_logo_url( $size );
	}

	/**
	 * Gets the site logo or site icon URL.
	 *
	 * @param string $size Attachment image size.
	 * @return string
	 */
	public function get_site_logo_url( $size = 'thumbnail' ) {
		$custom_logo_id = absint( get_theme_mod( 'custom_logo' ) );
		if ( $custom_logo_id ) {
			$logo_url = wp_get_attachment_image_url( $custom_logo_id, $size );
			if ( $logo_url ) {
				return $logo_url;
			}
		}

		$site_icon_url = get_site_icon_url( 128 );

		return $site_icon_url ? $site_icon_url : '';
	}

	/**
	 * Gets the custom logo attachment ID from settings.
	 *
	 * @return int
	 */
	public function get_custom_logo_id() {
		$settings = $this->get();

		return isset( $settings['logo_image_id'] ) ? absint( $settings['logo_image_id'] ) : 0;
	}

	/**
	 * Gets the custom background attachment ID from settings.
	 *
	 * @return int
	 */
	public function get_custom_background_id() {
		$settings = $this->get();

		return isset( $settings['background_image_id'] ) ? absint( $settings['background_image_id'] ) : 0;
	}

	/**
	 * Gets site initials for fallbacks.
	 *
	 * @return string
	 */
	public function get_site_initials() {
		$site_name = get_bloginfo( 'name' );
		$words     = preg_split( '/\s+/', trim( $site_name ) );
		$initials  = '';

		foreach ( $words as $word ) {
			if ( '' === $word ) {
				continue;
			}

			$initials .= strtoupper( substr( $word, 0, 1 ) );

			if ( 2 <= strlen( $initials ) ) {
				break;
			}
		}

		return $initials ? $initials : 'GB';
	}

	/**
	 * Gets default settings.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'message'             => __( 'Welcome to {site_name} Website - Admin Access Only!', 'greenberry' ),
			'background_image_id' => 0,
			'logo_image_id'       => 0,
		);
	}

	/**
	 * Sanitizes a media attachment ID and ensures it is an image.
	 *
	 * @param mixed $image_id Raw attachment ID.
	 * @return int
	 */
	private function sanitize_image_id( $image_id ) {
		$image_id = absint( $image_id );

		if ( ! $image_id || ! wp_attachment_is_image( $image_id ) ) {
			return 0;
		}

		return $image_id;
	}

	/**
	 * Gets an attachment URL if it exists.
	 *
	 * @param int    $image_id Attachment ID.
	 * @param string $size Attachment image size.
	 * @return string
	 */
	private function get_attachment_url( $image_id, $size ) {
		if ( ! $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, $size );

		return $url ? $url : '';
	}

	/**
	 * Deep-merges settings arrays.
	 *
	 * @param array $defaults Default values.
	 * @param array $settings Saved values.
	 * @return array
	 */
	private function deep_merge( $defaults, $settings ) {
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $value;
				continue;
			}

			if ( is_array( $value ) && is_array( $settings[ $key ] ) ) {
				$settings[ $key ] = $this->deep_merge( $value, $settings[ $key ] );
			}
		}

		return $settings;
	}
}
