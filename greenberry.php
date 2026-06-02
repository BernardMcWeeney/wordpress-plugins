<?php
/**
 * Plugin Name: Greenberry
 * Description: A modular WordPress plugin with a GDPR-aware Newsletter module.
 * Version: 0.1.0
 * Requires at least: 8.5
 * Requires PHP: 8.4
 * Author: Greenberry
 * Text Domain: greenberry
 *
 * @package Greenberry
 */

defined( 'ABSPATH' ) || exit;

define( 'GREENBERRY_VERSION', '0.1.0' );
define( 'GREENBERRY_PLUGIN_FILE', __FILE__ );
define( 'GREENBERRY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GREENBERRY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GREENBERRY_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Greenberry\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Greenberry\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Greenberry\\Plugin', 'init' ) );
