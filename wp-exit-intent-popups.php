<?php
/**
 * Plugin Name: Global Intent Popup
 * Plugin URI:
 * Description: Display exit intent and timed popups with A/B testing and GA4 integration.
 * Version: 1.0.0
 * Author:
 * License: GPL-2.0-or-later
 * Text Domain: wp-exit-intent-popups
 *
 * @package WP_Exit_Intent_Popups
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EIP_VERSION', '1.0.0' );
define( 'EIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EIP_DB_VERSION', '1.0' );

require_once EIP_PLUGIN_DIR . 'includes/class-post-type.php';
require_once EIP_PLUGIN_DIR . 'includes/class-popup-settings.php';
require_once EIP_PLUGIN_DIR . 'includes/class-page-assignment.php';
require_once EIP_PLUGIN_DIR . 'includes/class-frontend.php';
require_once EIP_PLUGIN_DIR . 'includes/class-ab-testing.php';
require_once EIP_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, 'eip_activate' );

/**
 * Plugin activation: create DB table and flush rewrite rules.
 */
function eip_activate() {
	EIP_Post_Type::register_post_type_static();
	EIP_AB_Testing::create_table();
	flush_rewrite_rules();
}

/**
 * Initialise all plugin classes.
 */
function eip_init() {
	( new EIP_Post_Type() )->register();
	( new EIP_Popup_Settings() )->register();
	( new EIP_Page_Assignment() )->register();
	( new EIP_Frontend() )->register();
	( new EIP_AB_Testing() )->register();
	( new EIP_Admin() )->register();
}
add_action( 'plugins_loaded', 'eip_init' );
