<?php
/**
 * Plugin Name:       Content Insights for Editors
 * Description:       Analyze content for your website
 * Version:           1.0.0
 * Author:            Whitespace
 * Text Domain:       content-insights-for-editors
 * Domain Path:       /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CIFE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CIFE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include the main plugin class
require_once CIFE_PLUGIN_PATH . 'includes/class-plugin.php';

// Initialize the plugin
function cife_init() {
    $plugin = new CIFE_Plugin();
    $plugin->run();
}
add_action('plugins_loaded', 'cife_init');