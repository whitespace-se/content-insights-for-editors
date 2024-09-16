<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'settings/class-settings-base.php';
require_once plugin_dir_path(__FILE__) . 'settings/class-settings-general.php';
require_once plugin_dir_path(__FILE__) . 'settings/class-settings-matomo.php';
require_once plugin_dir_path(__FILE__) . 'settings/class-settings-feedback.php';
require_once plugin_dir_path(__FILE__) . 'settings/class-settings-fetch-data.php';
require_once plugin_dir_path(__FILE__) . 'settings/class-settings-insights.php';
require_once CIFE_PLUGIN_PATH . 'includes/class-matomo.php';

class CIFE_Plugin {

    private $matomo;
    private $settings;

    public function __construct() {
        $this->matomo = new CIFE_Matomo();
        $this->settings = CIFE_Settings_Base::get_instance();
    }

    public function run() {
        try {
            // Load text domain for translations
            load_plugin_textdomain('content-insights-for-editors', false, dirname(plugin_basename(__FILE__)) . '/languages');

            // Enqueue admin styles and scripts
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // Register activation hook
            register_activation_hook(CIFE_PLUGIN_PATH . 'content-insights-for-editors.php', [$this, 'activate']);

            // Register deactivation hook
            register_deactivation_hook(CIFE_PLUGIN_PATH . 'content-insights-for-editors.php', [$this, 'deactivate']);

        } catch (Exception $e) {
            error_log('CIFE Plugin initialization error: ' . $e->getMessage());
        }
    }

    public function enqueue_admin_assets($hook) {
        // Enqueue on all admin pages
        wp_enqueue_style('cife-admin-css', CIFE_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.0');
        
        // Enqueue script only on the plugin's settings page
        if ('settings_page_content-insights-settings' === $hook) {
            wp_enqueue_script('cife-admin-js', CIFE_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], '1.0.0', true);
            wp_localize_script('cife-admin-js', 'cifeAjax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cife_fetch_matomo_nonce')
            ));
        }
    }

    public function activate() {
        // Create the table
        $this->matomo->create_table();
    }

    public function deactivate() {
        // We'll keep the table on deactivation
        // The table will be removed on uninstall
    }
}