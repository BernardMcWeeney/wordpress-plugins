<?php
/**
 * Admin Login page styling.
 *
 * @package Greenberry
 */

namespace Greenberry\AdminLogin;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the themed login while preserving WordPress login behavior.
 */
class Login {
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
	 * Registers login hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_filter( 'login_body_class', array( $this, 'body_class' ) );
		add_filter( 'login_headerurl', array( $this, 'header_url' ) );
		add_filter( 'login_headertext', array( $this, 'header_text' ) );
		add_filter( 'login_message', array( $this, 'login_message' ) );
	}

	/**
	 * Loads login styling.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'greenberry-admin-login',
			GREENBERRY_PLUGIN_URL . 'AdminLogin/login.css',
			array(),
			GREENBERRY_VERSION
		);

		wp_add_inline_style( 'greenberry-admin-login', $this->get_dynamic_css() );
	}

	/**
	 * Adds a body class for scoped CSS.
	 *
	 * @param array $classes Login body classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		$classes[] = 'greenberry-admin-login';

		return $classes;
	}

	/**
	 * Links the login logo to the site.
	 *
	 * @return string
	 */
	public function header_url() {
		return home_url( '/' );
	}

	/**
	 * Sets accessible logo text.
	 *
	 * @return string
	 */
	public function header_text() {
		return get_bloginfo( 'name' );
	}

	/**
	 * Adds a branded message on the normal login form only.
	 *
	 * @param string $message Existing login message.
	 * @return string
	 */
	public function login_message( $message ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		if ( '' !== $action && 'login' !== $action ) {
			return $message;
		}

		$custom_message = trim( $this->settings->get_message() );
		if ( '' === $custom_message ) {
			return $message;
		}

		return $message . '<div class="greenberry-login-message" role="note">' . nl2br( esc_html( $custom_message ) ) . '</div>';
	}

	/**
	 * Gets dynamic CSS variables and media URLs.
	 *
	 * @return string
	 */
	private function get_dynamic_css() {
		$css         = $this->get_palette_css();
		$background  = $this->settings->get_background_url();
		$logo        = $this->settings->get_logo_url( 'thumbnail' );
		$body_values = array();

		if ( $background ) {
			$body_values[] = "--greenberry-admin-login-bg-image: url('" . $this->get_css_url( $background ) . "')";
		}

		if ( $logo ) {
			$body_values[] = "--greenberry-admin-login-logo-image: url('" . $this->get_css_url( $logo ) . "')";
		}

		if ( ! empty( $body_values ) ) {
			$css .= 'body.login.greenberry-admin-login {' . implode( ';', $body_values ) . ';}';
		}

		return $css;
	}

	/**
	 * Builds CSS declarations for WordPress preset color variables.
	 *
	 * @return string
	 */
	private function get_palette_css() {
		$colors       = $this->get_color_defaults();
		$theme_colors = $this->get_theme_palette_colors();

		foreach ( $theme_colors as $slug => $color ) {
			if ( array_key_exists( $slug, $colors ) ) {
				$colors[ $slug ] = $color;
			}
		}

		$declarations = array();
		foreach ( $colors as $slug => $color ) {
			$declarations[] = '--wp--preset--color--' . $slug . ':' . $color;
		}

		return ':root, body.login.greenberry-admin-login {' . implode( ';', $declarations ) . ';}';
	}

	/**
	 * Escapes a URL for use inside single-quoted CSS url() values.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private function get_css_url( $url ) {
		$url = esc_url_raw( $url );

		return str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $url );
	}

	/**
	 * Gets fallback color values for the shared Greenberry preset variables.
	 *
	 * @return array<string,string>
	 */
	private function get_color_defaults() {
		return array(
			'base'       => '#ffffff',
			'base-2'     => '#f6f7f7',
			'contrast'   => '#1d2327',
			'contrast-2' => '#50575e',
			'contrast-3' => '#646970',
			'accent'     => '#2271b1',
			'accent-2'   => '#3858e9',
			'accent-3'   => '#d63638',
			'accent-4'   => '#008a20',
			'accent-5'   => '#dba617',
		);
	}

	/**
	 * Reads matching colors from the active theme global settings when available.
	 *
	 * @return array<string,string>
	 */
	private function get_theme_palette_colors() {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return array();
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$colors  = array();

		$this->collect_palette_colors( $palette, $colors );

		return $colors;
	}

	/**
	 * Recursively collects palette colors by slug.
	 *
	 * @param mixed $palette Palette data.
	 * @param array $colors Collected colors.
	 * @return void
	 */
	private function collect_palette_colors( $palette, &$colors ) {
		if ( ! is_array( $palette ) ) {
			return;
		}

		if ( isset( $palette['slug'], $palette['color'] ) ) {
			$slug  = sanitize_key( $palette['slug'] );
			$color = $this->sanitize_css_color( $palette['color'] );

			if ( $slug && $color ) {
				$colors[ $slug ] = $color;
			}

			return;
		}

		foreach ( $palette as $entry ) {
			$this->collect_palette_colors( $entry, $colors );
		}
	}

	/**
	 * Sanitizes a limited set of CSS color values used by WordPress palettes.
	 *
	 * @param mixed $color Raw color.
	 * @return string
	 */
	private function sanitize_css_color( $color ) {
		$color = trim( (string) $color );

		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $color ) ) {
			return $color;
		}

		if ( preg_match( '/^(rgb|rgba|hsl|hsla)\([0-9\s.,%+-]+\)$/i', $color ) ) {
			return $color;
		}

		if ( preg_match( '/^var\(--[a-z0-9_-]+\)$/i', $color ) ) {
			return $color;
		}

		return '';
	}
}
