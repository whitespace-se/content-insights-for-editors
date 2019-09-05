<?php

/**
 * Plugin Name:       Content Insights for Editors
 * Description:       Analyse content for your website
 * Version:           1.1.2
 * Author:            Johan Veeborn, Anders Rehn, Whitespace
 * Text Domain:       content-insights-for-editors
 * Domain Path:       /languages
 */

// Protect agains direct file access
if (!defined('WPINC')) {
	die();
}
define('CONTENT_INSIGHTS_FOR_EDITORS_PATH', plugin_dir_path(__FILE__));
define('CONTENT_INSIGHTS_FOR_EDITORS_URL', plugins_url('', __FILE__));
define(
	'CONTENT_INSIGHTS_FOR_EDITORS_MAIL_TEMPLATE_PATH',
	CONTENT_INSIGHTS_FOR_EDITORS_PATH . '/mail-templates'
);

add_action('plugins_loaded', function () {
	load_plugin_textdomain(
		'content-insights-for-editors',
		false,
		dirname(plugin_basename(__FILE__)) . '/languages'
	);
});
require_once CONTENT_INSIGHTS_FOR_EDITORS_PATH .
	'source/php/Vendor/Psr4ClassLoader.php';
if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
// Instantiate and register the autoloader
$loader = new CONTENT_INSIGHTS_FOR_EDITORS\Vendor\Psr4ClassLoader();
$loader->addPrefix('CONTENT_INSIGHTS_FOR_EDITORS', CONTENT_INSIGHTS_FOR_EDITORS_PATH);
$loader->addPrefix(
	'CONTENT_INSIGHTS_FOR_EDITORS',
	CONTENT_INSIGHTS_FOR_EDITORS_PATH . 'source/php/'
);
$loader->register();

add_action('plugins_loaded', function () {
	if ( !function_exists( 'register_fields_posttype_select' ) ) {
		require_once CONTENT_INSIGHTS_FOR_EDITORS_PATH .
			'plugins/acf-post-type-field/acf-posttype-select.php';
	}

	require_once CONTENT_INSIGHTS_FOR_EDITORS_PATH .
		'source/php/AcfFields/php/options-page.php';
}, 99);

register_deactivation_hook(__FILE__, 'cife_decativation');
if (!function_exists('cife_decativation')) {
	function cife_decativation() {
		wp_clear_scheduled_hook('cife_cron_mail');
	}
}

// Start application
new \CONTENT_INSIGHTS_FOR_EDITORS\App();
