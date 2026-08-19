<?php
/**
 * Plugin Name: Ichimaru Core
 * Plugin URI: https://ichimaruudon.com/
 * Description: Core functionality and data structures for Ichimaru Udon (restaurant locations, menu data, and other content that should survive a theme change).
 * Version: 1.0.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Ichimaru Udon
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ichimaru-core
 */

defined( 'ABSPATH' ) || exit;

define( 'ICHIMARU_CORE_VERSION', '1.0.1' );
define( 'ICHIMARU_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ICHIMARU_CORE_URL', plugins_url( '/', __FILE__ ) );

/**
 * Load the plugin textdomain.
 */
function ichimaru_core_load_textdomain() {
	load_plugin_textdomain( 'ichimaru-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'ichimaru_core_load_textdomain' );

require_once ICHIMARU_CORE_DIR . 'includes/menu-items.php';

/**
 * Flush rewrite rules once on activation so future custom post types/taxonomies register cleanly.
 */
function ichimaru_core_activate() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ichimaru_core_activate' );

/**
 * Flush rewrite rules on deactivation to remove any registered post type/taxonomy routes.
 */
function ichimaru_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ichimaru_core_deactivate' );
