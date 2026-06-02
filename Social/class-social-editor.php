<?php
/**
 * Social block editor integration.
 *
 * @package Greenberry
 */

namespace Greenberry\Social;

defined( 'ABSPATH' ) || exit;

/**
 * Registers post meta and editor assets for Social controls.
 */
class Editor {
	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers Social post meta for REST/block editor use.
	 *
	 * @return void
	 */
	public function register_post_meta() {
		foreach ( $this->settings->get_publishable_post_types() as $post_type => $object ) {
			register_post_meta(
				$post_type,
				Publisher::META_ENABLED,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => 'inherit',
					'sanitize_callback' => array( $this, 'sanitize_publish_mode' ),
					'show_in_rest'      => true,
					'auth_callback'     => array( $this, 'can_edit_meta' ),
				)
			);

			register_post_meta(
				$post_type,
				Publisher::META_CHANNELS,
				array(
					'type'              => 'array',
					'single'            => true,
					'default'           => array(),
					'sanitize_callback' => array( $this, 'sanitize_channels' ),
					'show_in_rest'      => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
					),
					'auth_callback'     => array( $this, 'can_edit_meta' ),
				)
			);

			register_post_meta(
				$post_type,
				Publisher::META_MESSAGE,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'sanitize_callback' => 'sanitize_textarea_field',
					'show_in_rest'      => true,
					'auth_callback'     => array( $this, 'can_edit_meta' ),
				)
			);
		}
	}

	/**
	 * Enqueues editor panel assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_script(
			'greenberry-social-editor',
			GREENBERRY_PLUGIN_URL . 'Social/social-editor.js',
			array( 'wp-components', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			GREENBERRY_VERSION,
			true
		);

		wp_localize_script(
			'greenberry-social-editor',
			'greenberrySocialEditor',
			$this->settings->get_editor_data()
		);

		wp_enqueue_style(
			'greenberry-social-editor',
			GREENBERRY_PLUGIN_URL . 'Social/social-editor.css',
			array(),
			GREENBERRY_VERSION
		);
	}

	/**
	 * Sanitizes publish mode meta.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public function sanitize_publish_mode( $value ) {
		$value = sanitize_key( $value );

		return in_array( $value, array( 'inherit', 'on', 'off' ), true ) ? $value : 'inherit';
	}

	/**
	 * Sanitizes selected channels.
	 *
	 * @param mixed $value Value.
	 * @return array<int,string>
	 */
	public function sanitize_channels( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = array_keys( $this->settings->providers() );
		$value   = array_map( 'sanitize_key', $value );
		$value   = array_intersect( $value, $allowed );

		return array_values( array_unique( $value ) );
	}

	/**
	 * Checks meta edit permissions.
	 *
	 * @param bool   $allowed Existing permission.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id Post ID.
	 * @return bool
	 */
	public function can_edit_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', absint( $post_id ) );
	}
}
