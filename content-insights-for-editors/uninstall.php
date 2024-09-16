<?php

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load the Matomo class
require_once plugin_dir_path(__FILE__) . 'includes/class-matomo.php';

// Create an instance of the Matomo class
$matomo = new CIFE_Matomo();

// Drop the table
$matomo->drop_tables();

// Delete any options your plugin may have created
delete_option('cife_matomo_api_url');
delete_option('cife_matomo_site_id');
delete_option('cife_matomo_api_key');
// Add any other options your plugin might have created