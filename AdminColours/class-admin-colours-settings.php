<?php
/**
 * Admin Colours settings.
 *
 * @package Greenberry
 */

namespace Greenberry\AdminColours;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and normalizes Admin Colours configuration.
 */
class Settings {
	const OPTION_NAME = 'greenberry_admin_colours_settings';
	const SCHEME_KEY  = 'greenberry-admin-colours';

	const SOURCE_THEME  = 'theme';
	const SOURCE_CUSTOM = 'custom';

	private const PRESET_FALLBACKS = array(
		'base'       => '#ffffff',
		'base-2'     => '#f1f1f1',
		'contrast'   => '#23282d',
		'contrast-2' => '#1d2327',
		'contrast-3' => '#000000',
		'accent'     => '#2b5995',
		'accent-2'   => '#04326e',
		'accent-3'   => '#113f7b',
		'accent-4'   => '#4472ae',
		'accent-5'   => '#d54e21',
	);

	private const CUSTOM_COLOUR_KEYS = array(
		'background',
		'menu_background',
		'submenu_background',
		'menu_text',
		'accent',
		'accent_hover',
		'accent_active',
		'accent_soft',
		'notification',
	);

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

		$settings = $this->deep_merge( $this->defaults(), $settings );

		$settings['source'] = self::SOURCE_CUSTOM === $settings['source'] ? self::SOURCE_CUSTOM : self::SOURCE_THEME;

		foreach ( self::CUSTOM_COLOUR_KEYS as $key ) {
			$settings['custom_colours'][ $key ] = $this->sanitize_hex(
				isset( $settings['custom_colours'][ $key ] ) ? $settings['custom_colours'][ $key ] : '',
				$this->default_custom_colours()[ $key ]
			);
		}

		return $settings;
	}

	/**
	 * Saves settings from admin form data.
	 *
	 * @param array $data Raw form data.
	 * @return void
	 */
	public function save( $data ) {
		$existing       = $this->get();
		$posted_colours = isset( $data['custom_colours'] ) && is_array( $data['custom_colours'] ) ? $data['custom_colours'] : array();
		$posted_source  = isset( $data['source'] ) && is_string( $data['source'] ) ? sanitize_key( $data['source'] ) : self::SOURCE_THEME;
		$custom_colours = array();

		foreach ( self::CUSTOM_COLOUR_KEYS as $key ) {
			$fallback = isset( $existing['custom_colours'][ $key ] ) ? $existing['custom_colours'][ $key ] : $this->default_custom_colours()[ $key ];
			$value    = isset( $posted_colours[ $key ] ) ? $posted_colours[ $key ] : $fallback;

			$custom_colours[ $key ] = $this->sanitize_hex( $value, $fallback );
		}

		update_option(
			self::OPTION_NAME,
			array(
				'source'         => self::SOURCE_CUSTOM === $posted_source ? self::SOURCE_CUSTOM : self::SOURCE_THEME,
				'custom_colours' => $custom_colours,
			),
			false
		);
	}

	/**
	 * Resets settings to the theme preset defaults.
	 *
	 * @return void
	 */
	public function reset() {
		delete_option( self::OPTION_NAME );
		$this->ensure_defaults();
	}

	/**
	 * Returns custom colour field metadata for the settings screen.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_custom_colour_fields() {
		return array(
			'background'         => array(
				'label'       => __( 'Admin page background', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--base-2.', 'greenberry' ),
			),
			'menu_background'    => array(
				'label'       => __( 'Menu and admin bar background', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--contrast.', 'greenberry' ),
			),
			'submenu_background' => array(
				'label'       => __( 'Submenu background', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--contrast-3.', 'greenberry' ),
			),
			'menu_text'          => array(
				'label'       => __( 'Menu text', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--base.', 'greenberry' ),
			),
			'accent'             => array(
				'label'       => __( 'Accent', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--accent.', 'greenberry' ),
			),
			'accent_hover'       => array(
				'label'       => __( 'Accent hover', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--accent-2.', 'greenberry' ),
			),
			'accent_active'      => array(
				'label'       => __( 'Accent active', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--accent-3.', 'greenberry' ),
			),
			'accent_soft'        => array(
				'label'       => __( 'Accent soft', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--accent-4.', 'greenberry' ),
			),
			'notification'       => array(
				'label'       => __( 'Notification badges', 'greenberry' ),
				'description' => __( 'Default preset: --wp--preset--color--accent-5.', 'greenberry' ),
			),
		);
	}

	/**
	 * Returns the WordPress preset colour variables used by default.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_preset_tokens() {
		$resolved = $this->get_resolved_preset_colours();
		$tokens   = array();

		foreach ( self::PRESET_FALLBACKS as $slug => $fallback ) {
			$tokens[ $slug ] = array(
				'variable' => '--wp--preset--color--' . $slug,
				'colour'   => isset( $resolved[ $slug ] ) ? $resolved[ $slug ] : $fallback,
				'fallback' => $fallback,
			);
		}

		return $tokens;
	}

	/**
	 * Gets resolved hex colours for WordPress's admin scheme swatches.
	 *
	 * @return array<int,string>
	 */
	public function get_admin_scheme_swatches() {
		$colours = $this->get_colours();

		return array(
			$colours['menu_background'],
			$colours['menu_text'],
			$colours['accent'],
			$colours['notification'],
		);
	}

	/**
	 * Gets icon colours for WordPress's admin scheme registry.
	 *
	 * @return array<string,string>
	 */
	public function get_icon_colours() {
		$colours = $this->get_colours();

		return array(
			'base'    => $colours['menu_text'],
			'focus'   => $colours['accent'],
			'current' => $colours['menu_text'],
		);
	}

	/**
	 * Gets resolved hex colours for labels, swatches, and non-variable CSS.
	 *
	 * @return array<string,string>
	 */
	public function get_colours() {
		$settings = $this->get();

		if ( self::SOURCE_CUSTOM === $settings['source'] ) {
			$colours = $settings['custom_colours'];
		} else {
			$presets = $this->get_resolved_preset_colours();
			$colours = array(
				'background'         => $presets['base-2'],
				'menu_background'    => $presets['contrast'],
				'submenu_background' => $presets['contrast-3'],
				'menu_text'          => $presets['base'],
				'accent'             => $presets['accent'],
				'accent_hover'       => $presets['accent-2'],
				'accent_active'      => $presets['accent-3'],
				'accent_soft'        => $presets['accent-4'],
				'notification'       => $presets['accent-5'],
			);
		}

		$colours['on_accent']       = $this->get_readable_text_colour( $colours['accent'] );
		$colours['on_notification'] = $this->get_readable_text_colour( $colours['notification'] );

		return $colours;
	}

	/**
	 * Gets CSS custom property values for the active colour source.
	 *
	 * @return array<string,string>
	 */
	public function get_css_tokens() {
		$settings = $this->get();

		if ( self::SOURCE_CUSTOM === $settings['source'] ) {
			$colours = $this->get_colours();

			return array(
				'background'         => $colours['background'],
				'menu_background'    => $colours['menu_background'],
				'submenu_background' => $colours['submenu_background'],
				'menu_text'          => $colours['menu_text'],
				'accent'             => $colours['accent'],
				'accent_hover'       => $colours['accent_hover'],
				'accent_active'      => $colours['accent_active'],
				'accent_soft'        => $colours['accent_soft'],
				'notification'       => $colours['notification'],
				'on_accent'          => $colours['on_accent'],
				'on_notification'    => $colours['on_notification'],
			);
		}

		$presets = $this->get_resolved_preset_colours();
		$colours = $this->get_colours();

		return array(
			'background'         => $this->preset_var( 'base-2', $presets['base-2'] ),
			'menu_background'    => $this->preset_var( 'contrast', $presets['contrast'] ),
			'submenu_background' => $this->preset_var( 'contrast-3', $presets['contrast-3'] ),
			'menu_text'          => $this->preset_var( 'base', $presets['base'] ),
			'accent'             => $this->preset_var( 'accent', $presets['accent'] ),
			'accent_hover'       => $this->preset_var( 'accent-2', $presets['accent-2'] ),
			'accent_active'      => $this->preset_var( 'accent-3', $presets['accent-3'] ),
			'accent_soft'        => $this->preset_var( 'accent-4', $presets['accent-4'] ),
			'notification'       => $this->preset_var( 'accent-5', $presets['accent-5'] ),
			'on_accent'          => $colours['on_accent'],
			'on_notification'    => $colours['on_notification'],
		);
	}

	/**
	 * Builds inline CSS variables and colour-sensitive SVG rules.
	 *
	 * @return string
	 */
	public function get_inline_css() {
		$tokens       = $this->get_css_tokens();
		$colours      = $this->get_colours();
		$checkbox_uri = $this->get_checkbox_icon_data_uri( $colours['accent'] );
		$css          = ":root {\n";

		foreach ( $tokens as $key => $value ) {
			$css .= "\t--greenberry-admin-" . str_replace( '_', '-', $key ) . ': ' . $value . ";\n";
		}

		$css .= "}\n";
		$css .= 'input[type="checkbox"]:checked::before {' . "\n";
		$css .= "\tcontent: url(\"" . $checkbox_uri . "\");\n";
		$css .= "}\n";

		return $css;
	}

	/**
	 * Gets resolved colours for the configured WordPress preset slugs.
	 *
	 * @return array<string,string>
	 */
	public function get_resolved_preset_colours() {
		$theme_colours = $this->get_theme_palette_colours();
		$resolved      = array();

		foreach ( self::PRESET_FALLBACKS as $slug => $fallback ) {
			$resolved[ $slug ] = isset( $theme_colours[ $slug ] ) ? $theme_colours[ $slug ] : $fallback;
		}

		return $resolved;
	}

	/**
	 * Gets default settings.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'source'         => self::SOURCE_THEME,
			'custom_colours' => $this->default_custom_colours(),
		);
	}

	/**
	 * Gets custom colour defaults derived from the configured presets.
	 *
	 * @return array<string,string>
	 */
	private function default_custom_colours() {
		$presets = $this->get_resolved_preset_colours();

		return array(
			'background'         => $presets['base-2'],
			'menu_background'    => $presets['contrast'],
			'submenu_background' => $presets['contrast-3'],
			'menu_text'          => $presets['base'],
			'accent'             => $presets['accent'],
			'accent_hover'       => $presets['accent-2'],
			'accent_active'      => $presets['accent-3'],
			'accent_soft'        => $presets['accent-4'],
			'notification'       => $presets['accent-5'],
		);
	}

	/**
	 * Builds a safe CSS var() reference with a hex fallback.
	 *
	 * @param string $slug Preset slug.
	 * @param string $fallback Fallback hex colour.
	 * @return string
	 */
	private function preset_var( $slug, $fallback ) {
		if ( ! array_key_exists( $slug, self::PRESET_FALLBACKS ) ) {
			return $this->sanitize_hex( $fallback, '#000000' );
		}

		return 'var(--wp--preset--color--' . $slug . ', ' . $this->sanitize_hex( $fallback, self::PRESET_FALLBACKS[ $slug ] ) . ')';
	}

	/**
	 * Gets theme palette colours keyed by slug.
	 *
	 * @return array<string,string>
	 */
	private function get_theme_palette_colours() {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return array();
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		if ( ! is_array( $palette ) ) {
			return array();
		}

		$colours = array();
		foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
			if ( empty( $palette[ $origin ] ) || ! is_array( $palette[ $origin ] ) ) {
				continue;
			}

			foreach ( $palette[ $origin ] as $item ) {
				if ( empty( $item['slug'] ) || empty( $item['color'] ) ) {
					continue;
				}

				$slug   = sanitize_key( $item['slug'] );
				$colour = $this->sanitize_hex( $item['color'], '' );

				if ( '' !== $colour && ! isset( $colours[ $slug ] ) ) {
					$colours[ $slug ] = $colour;
				}
			}
		}

		return $colours;
	}

	/**
	 * Sanitizes and normalizes a hex colour.
	 *
	 * @param mixed  $value Colour value.
	 * @param string $fallback Fallback colour.
	 * @return string
	 */
	private function sanitize_hex( $value, $fallback ) {
		$colour = is_string( $value ) ? sanitize_hex_color( $value ) : '';

		if ( ! $colour ) {
			$colour = is_string( $fallback ) ? sanitize_hex_color( $fallback ) : '';
		}

		return $this->normalize_hex( $colour );
	}

	/**
	 * Normalizes shorthand hex colours to six characters.
	 *
	 * @param string $colour Hex colour.
	 * @return string
	 */
	private function normalize_hex( $colour ) {
		if ( ! is_string( $colour ) || '' === $colour ) {
			return '';
		}

		if ( preg_match( '/^#([0-9a-f]{3})$/i', $colour, $matches ) ) {
			return strtolower( '#' . $matches[1][0] . $matches[1][0] . $matches[1][1] . $matches[1][1] . $matches[1][2] . $matches[1][2] );
		}

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $colour ) ) {
			return strtolower( $colour );
		}

		return '';
	}

	/**
	 * Gets readable black or white text for a background colour.
	 *
	 * @param string $background Background hex colour.
	 * @return string
	 */
	private function get_readable_text_colour( $background ) {
		$rgb = $this->hex_to_rgb( $background );
		if ( ! $rgb ) {
			return '#ffffff';
		}

		$luminance = ( ( 0.299 * $rgb['r'] ) + ( 0.587 * $rgb['g'] ) + ( 0.114 * $rgb['b'] ) ) / 255;

		return $luminance > 0.56 ? '#000000' : '#ffffff';
	}

	/**
	 * Converts a hex colour to RGB channels.
	 *
	 * @param string $colour Hex colour.
	 * @return array<string,int>|null
	 */
	private function hex_to_rgb( $colour ) {
		$colour = $this->sanitize_hex( $colour, '' );
		if ( '' === $colour ) {
			return null;
		}

		return array(
			'r' => hexdec( substr( $colour, 1, 2 ) ),
			'g' => hexdec( substr( $colour, 3, 2 ) ),
			'b' => hexdec( substr( $colour, 5, 2 ) ),
		);
	}

	/**
	 * Builds an encoded checkbox tick icon with the active accent colour.
	 *
	 * @param string $colour Hex colour.
	 * @return string
	 */
	private function get_checkbox_icon_data_uri( $colour ) {
		$fill = rawurlencode( $this->sanitize_hex( $colour, self::PRESET_FALLBACKS['accent'] ) );

		return 'data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20viewBox%3D%270%200%2020%2020%27%3E%3Cpath%20d%3D%27M14.83%204.89l1.34.94-5.81%208.38H9.02L5.78%209.67l1.34-1.25%202.57%202.4z%27%20fill%3D%27' . $fill . '%27%2F%3E%3C%2Fsvg%3E';
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
