<?php
/**
 * Social module settings.
 *
 * @package Greenberry
 */

namespace Greenberry\Social;

defined( 'ABSPATH' ) || exit;

/**
 * Stores provider credentials, publishing rules, and activity logs.
 */
class Settings {
	const OPTION_NAME     = 'greenberry_social_settings';
	const LOG_OPTION_NAME = 'greenberry_social_log';

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
	 * Gets normalized Social settings.
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
	 * Saves Social settings from admin form data.
	 *
	 * @param array $data Raw form data.
	 * @return void
	 */
	public function save( $data ) {
		$existing = $this->get();
		$clean    = $this->sanitize_settings( $data, $existing );

		update_option( self::OPTION_NAME, $clean, false );
	}

	/**
	 * Saves settings for one social provider.
	 *
	 * @param string $provider      Provider key.
	 * @param array  $provider_data Raw provider form data.
	 * @param bool   $clear_token   Whether to clear the saved token.
	 * @return array|\WP_Error
	 */
	public function save_provider( $provider, $provider_data, $clear_token = false ) {
		$provider = sanitize_key( $provider );
		if ( ! array_key_exists( $provider, $this->providers() ) ) {
			return new \WP_Error( 'unsupported_provider', __( 'Unsupported social provider.', 'greenberry' ) );
		}

		$existing = $this->get();
		$data     = array(
			'providers'   => $existing['providers'],
			'clear_token' => array(),
		);

		$data['providers'][ $provider ] = is_array( $provider_data ) ? $provider_data : array();

		if ( $clear_token ) {
			$data['clear_token'][ $provider ] = true;
		}

		$providers = $this->sanitize_providers( $data, $existing );
		$existing['providers'][ $provider ] = $providers[ $provider ];

		update_option( self::OPTION_NAME, $existing, false );

		return $existing['providers'][ $provider ];
	}

	/**
	 * Stores the last connection-test outcome for a provider.
	 *
	 * @param string $provider Provider key.
	 * @param bool   $success  Whether the test succeeded.
	 * @param string $message  Failure message.
	 * @return array|\WP_Error
	 */
	public function record_provider_test( $provider, $success, $message = '' ) {
		$provider = sanitize_key( $provider );
		if ( ! array_key_exists( $provider, $this->providers() ) ) {
			return new \WP_Error( 'unsupported_provider', __( 'Unsupported social provider.', 'greenberry' ) );
		}

		$settings = $this->get();
		if ( ! isset( $settings['providers'][ $provider ] ) || ! is_array( $settings['providers'][ $provider ] ) ) {
			return new \WP_Error( 'unsupported_provider', __( 'Unsupported social provider.', 'greenberry' ) );
		}

		$settings['providers'][ $provider ]['verified_at'] = $success ? current_time( 'mysql' ) : '';
		$settings['providers'][ $provider ]['last_error']  = $success ? '' : sanitize_text_field( $message );

		update_option( self::OPTION_NAME, $settings, false );

		return $settings['providers'][ $provider ];
	}

	/**
	 * Returns provider definitions.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function providers() {
		return array(
			'bluesky' => array(
				'label'       => __( 'Bluesky', 'greenberry' ),
				'description' => __( 'Publish text posts through the Bluesky AT Protocol API.', 'greenberry' ),
			),
			'linkedin' => array(
				'label'       => __( 'LinkedIn', 'greenberry' ),
				'description' => __( 'Publish organic text posts through the LinkedIn Posts API.', 'greenberry' ),
			),
		);
	}

	/**
	 * Returns public data for the editor panel.
	 *
	 * @return array
	 */
	public function get_editor_data() {
		$settings = $this->get();
		$providers = array();

		foreach ( $this->providers() as $key => $provider ) {
			$status = $this->get_provider_status( $key, $settings );
			$providers[ $key ] = array(
				'label'       => $provider['label'],
				'description' => $provider['description'],
				'enabled'     => ! empty( $settings['providers'][ $key ]['enabled'] ),
				'ready'       => $status['ready'],
				'status'      => $status['label'],
			);
		}

		return array(
			'enabled'          => ! empty( $settings['enabled'] ),
			'messageTemplate'  => $settings['message_template'],
			'defaultChannels'  => $this->get_ready_default_channels( $settings ),
			'providers'        => $providers,
			'siteName'         => get_bloginfo( 'name' ),
			'homeUrl'          => home_url( '/' ),
			'logoUrl'          => $this->get_site_logo_url(),
			'settingsUrl'      => admin_url( 'admin.php?page=greenberry-social' ),
			'publishModeMeta'  => 'greenberry_social_enabled',
			'channelsMeta'     => 'greenberry_social_channels',
			'messageMeta'      => 'greenberry_social_message',
		);
	}

	/**
	 * Gets ready/enabled default channels.
	 *
	 * @param array|null $settings Settings.
	 * @return array<int,string>
	 */
	public function get_enabled_default_channels( $settings = null ) {
		if ( null === $settings ) {
			$settings = $this->get();
		}

		$channels = array();
		foreach ( $this->providers() as $key => $provider ) {
			if ( ! empty( $settings['default_channels'][ $key ] ) ) {
				$channels[] = $key;
			}
		}

		return $channels;
	}

	/**
	 * Gets default channels that are ready for editor previews.
	 *
	 * @param array $settings Settings.
	 * @return array<int,string>
	 */
	private function get_ready_default_channels( $settings ) {
		$channels = array();
		foreach ( $this->get_enabled_default_channels( $settings ) as $provider ) {
			$status = $this->get_provider_status( $provider, $settings );
			if ( ! empty( $status['ready'] ) ) {
				$channels[] = $provider;
			}
		}

		return $channels;
	}

	/**
	 * Gets one provider status.
	 *
	 * @param string     $provider Provider key.
	 * @param array|null $settings Settings.
	 * @return array{ready:bool,label:string,state:string}
	 */
	public function get_provider_status( $provider, $settings = null ) {
		if ( null === $settings ) {
			$settings = $this->get();
		}

		$config = isset( $settings['providers'][ $provider ] ) && is_array( $settings['providers'][ $provider ] )
			? $settings['providers'][ $provider ]
			: array();

		if ( empty( $config['enabled'] ) ) {
			return array(
				'ready' => false,
				'label' => __( 'Disabled', 'greenberry' ),
				'state' => 'disabled',
			);
		}

		if ( 'bluesky' === $provider ) {
			$ready = ! empty( $config['identifier'] ) && ! empty( $config['token'] ) && ! empty( $config['pds_host'] );
		} elseif ( 'linkedin' === $provider ) {
			$ready = ! empty( $config['token'] ) && ! empty( $config['author_urn'] );
		} else {
			$ready = false;
		}

		if ( ! $ready ) {
			return array(
				'ready' => false,
				'label' => __( 'Missing details', 'greenberry' ),
				'state' => 'missing',
			);
		}

		if ( ! empty( $config['last_error'] ) ) {
			return array(
				'ready' => false,
				'label' => __( 'Check failed', 'greenberry' ),
				'state' => 'error',
			);
		}

		if ( ! empty( $config['verified_at'] ) ) {
			return array(
				'ready' => true,
				'label' => __( 'Verified', 'greenberry' ),
				'state' => 'verified',
			);
		}

		return array(
			'ready' => true,
			'label' => __( 'Configured', 'greenberry' ),
			'state' => 'configured',
		);
	}

	/**
	 * Gets public post types that can be selected for Social publishing.
	 *
	 * @return array<string,\WP_Post_Type>
	 */
	public function get_publishable_post_types() {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		unset( $post_types['attachment'] );

		return $post_types;
	}

	/**
	 * Adds one activity log row.
	 *
	 * @param array $entry Log entry.
	 * @return void
	 */
	public function add_log_entry( $entry ) {
		$log = get_option( self::LOG_OPTION_NAME, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			wp_parse_args(
				$entry,
				array(
					'time'        => current_time( 'mysql' ),
					'post_id'     => 0,
					'post_title'  => '',
					'provider'    => '',
					'status'      => '',
					'external_id' => '',
					'url'         => '',
					'message'     => '',
					'source'      => 'automatic',
				)
			)
		);

		$log = array_slice( $log, 0, 50 );
		update_option( self::LOG_OPTION_NAME, $log, false );
	}

	/**
	 * Gets recent activity log entries.
	 *
	 * @return array<int,array>
	 */
	public function get_log_entries() {
		$log = get_option( self::LOG_OPTION_NAME, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Gets default settings.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'enabled'          => true,
			'message_template' => "{site_name}: {post_title}\n{post_url}",
			'default_channels' => array(
				'bluesky'  => true,
				'linkedin' => true,
			),
			'providers'        => array(
				'bluesky'  => array(
					'enabled'    => false,
					'identifier' => '',
					'token'      => '',
					'pds_host'   => 'https://bsky.social',
					'verified_at' => '',
					'last_error' => '',
				),
				'linkedin' => array(
					'enabled'    => false,
					'token'      => '',
					'author_urn' => '',
					'version'    => '202603',
					'verified_at' => '',
					'last_error' => '',
				),
			),
			'rules'            => array(
				'post_types' => array( 'post' ),
				'categories' => array(),
				'tags'       => array(),
			),
		);
	}

	/**
	 * Sanitizes a complete settings payload.
	 *
	 * @param array $data Raw data.
	 * @param array $existing Existing settings.
	 * @return array
	 */
	private function sanitize_settings( $data, $existing ) {
		$allowed_post_types = array_keys( $this->get_publishable_post_types() );
		$post_types         = isset( $data['post_types'] ) && is_array( $data['post_types'] ) ? $data['post_types'] : array();
		$post_types         = array_values( array_intersect( array_map( 'sanitize_key', $post_types ), $allowed_post_types ) );
		$default_channels   = isset( $data['default_channels'] ) && is_array( $data['default_channels'] ) ? $data['default_channels'] : array();

		if ( empty( $post_types ) ) {
			$post_types = array( 'post' );
		}

		$template = isset( $data['message_template'] ) ? sanitize_textarea_field( $data['message_template'] ) : '';
		if ( '' === trim( $template ) ) {
			$template = $this->defaults()['message_template'];
		}

		$providers = $this->sanitize_providers( $data, $existing );

		return array(
			'enabled'          => ! empty( $data['enabled'] ),
			'message_template' => $template,
			'default_channels' => array(
				'bluesky'  => ! empty( $default_channels['bluesky'] ),
				'linkedin' => ! empty( $default_channels['linkedin'] ),
			),
			'providers'        => $providers,
			'rules'            => array(
				'post_types' => $post_types,
				'categories' => $this->sanitize_int_array( isset( $data['categories'] ) ? $data['categories'] : array() ),
				'tags'       => $this->sanitize_int_array( isset( $data['tags'] ) ? $data['tags'] : array() ),
			),
		);
	}

	/**
	 * Sanitizes provider settings while preserving hidden tokens.
	 *
	 * @param array $data Raw data.
	 * @param array $existing Existing settings.
	 * @return array
	 */
	private function sanitize_providers( $data, $existing ) {
		$provider_data = isset( $data['providers'] ) && is_array( $data['providers'] ) ? $data['providers'] : array();
		$clear_token   = isset( $data['clear_token'] ) && is_array( $data['clear_token'] ) ? $data['clear_token'] : array();
		$defaults      = $this->defaults()['providers'];

		$bluesky = isset( $provider_data['bluesky'] ) && is_array( $provider_data['bluesky'] ) ? $provider_data['bluesky'] : array();
		$linkedin = isset( $provider_data['linkedin'] ) && is_array( $provider_data['linkedin'] ) ? $provider_data['linkedin'] : array();

		$bluesky_token = isset( $existing['providers']['bluesky']['token'] ) ? $existing['providers']['bluesky']['token'] : '';
		if ( ! empty( $clear_token['bluesky'] ) ) {
			$bluesky_token = '';
		} elseif ( isset( $bluesky['token'] ) && '' !== trim( (string) $bluesky['token'] ) ) {
			$bluesky_token = sanitize_text_field( $bluesky['token'] );
		}

		$linkedin_token = isset( $existing['providers']['linkedin']['token'] ) ? $existing['providers']['linkedin']['token'] : '';
		if ( ! empty( $clear_token['linkedin'] ) ) {
			$linkedin_token = '';
		} elseif ( isset( $linkedin['token'] ) && '' !== trim( (string) $linkedin['token'] ) ) {
			$linkedin_token = sanitize_text_field( $linkedin['token'] );
		}

		$pds_host = isset( $bluesky['pds_host'] ) ? esc_url_raw( $bluesky['pds_host'] ) : $defaults['bluesky']['pds_host'];
		$pds_host = untrailingslashit( $pds_host );
		if ( '' === $pds_host ) {
			$pds_host = $defaults['bluesky']['pds_host'];
		}

		$linkedin_version = isset( $linkedin['version'] ) ? preg_replace( '/[^0-9]/', '', (string) $linkedin['version'] ) : '';
		if ( 6 !== strlen( $linkedin_version ) ) {
			$linkedin_version = $defaults['linkedin']['version'];
		}

		$providers = array(
			'bluesky'  => array(
				'enabled'    => ! empty( $bluesky['enabled'] ),
				'identifier' => isset( $bluesky['identifier'] ) ? sanitize_text_field( $bluesky['identifier'] ) : '',
				'token'      => $bluesky_token,
				'pds_host'   => $pds_host,
			),
			'linkedin' => array(
				'enabled'    => ! empty( $linkedin['enabled'] ),
				'token'      => $linkedin_token,
				'author_urn' => isset( $linkedin['author_urn'] ) ? sanitize_text_field( $linkedin['author_urn'] ) : '',
				'version'    => $linkedin_version,
			),
		);

		foreach ( $providers as $provider => $config ) {
			$providers[ $provider ] = $this->preserve_connection_check( $provider, $config, $existing );
		}

		return $providers;
	}

	/**
	 * Preserves the last connection check until the saved connection changes.
	 *
	 * @param string $provider Provider key.
	 * @param array  $config   Sanitized provider config without check state.
	 * @param array  $existing Existing settings.
	 * @return array
	 */
	private function preserve_connection_check( $provider, $config, $existing ) {
		$existing_config = isset( $existing['providers'][ $provider ] ) && is_array( $existing['providers'][ $provider ] )
			? $existing['providers'][ $provider ]
			: array();

		$existing_base = array();
		foreach ( array_keys( $config ) as $key ) {
			$existing_base[ $key ] = isset( $existing_config[ $key ] ) ? $existing_config[ $key ] : '';
		}

		if ( $existing_base === $config ) {
			$config['verified_at'] = isset( $existing_config['verified_at'] ) ? sanitize_text_field( $existing_config['verified_at'] ) : '';
			$config['last_error']  = isset( $existing_config['last_error'] ) ? sanitize_text_field( $existing_config['last_error'] ) : '';
			return $config;
		}

		$config['verified_at'] = '';
		$config['last_error']  = '';

		return $config;
	}

	/**
	 * Sanitizes an integer array.
	 *
	 * @param mixed $values Values.
	 * @return array<int,int>
	 */
	private function sanitize_int_array( $values ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		$values = array_map( 'absint', $values );
		$values = array_filter( $values );

		return array_values( array_unique( $values ) );
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

			if ( is_array( $value ) && ! is_array( $settings[ $key ] ) ) {
				$settings[ $key ] = $value;
				continue;
			}

			if ( is_array( $value ) ) {
				$settings[ $key ] = $this->deep_merge( $value, $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Gets a site logo URL for branded previews.
	 *
	 * @return string
	 */
	private function get_site_logo_url() {
		$custom_logo_id = absint( get_theme_mod( 'custom_logo' ) );
		if ( $custom_logo_id ) {
			$logo = wp_get_attachment_image_url( $custom_logo_id, 'thumbnail' );
			if ( $logo ) {
				return $logo;
			}
		}

		$site_icon = get_site_icon_url( 96 );

		return $site_icon ? $site_icon : '';
	}
}
