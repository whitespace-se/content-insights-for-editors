<?php
class CIFE_Settings_Fetch_Data {
    public static function init() {
        add_action('wp_ajax_cife_fetch_matomo_data', [__CLASS__, 'ajax_fetch_matomo_data']);
        add_action('wp_ajax_cife_empty_matomo_data', [__CLASS__, 'ajax_empty_matomo_data']);
    }

    public static function render() {
        ?>
        <div class="wrap">
            <h2><?php _e('Fetch Matomo Data', 'content-insights-for-editors'); ?></h2>
            <p><?php _e('Click the button below to fetch the latest data from Matomo.', 'content-insights-for-editors'); ?></p>
            <button id="fetch_matomo_data" class="button button-primary"><?php _e('Fetch Matomo Data', 'content-insights-for-editors'); ?></button>
            <div id="fetch_matomo_data-result"></div>

            <h2><?php _e('Empty Matomo Data', 'content-insights-for-editors'); ?></h2>
            <p><?php _e('Click the button below to empty all fetched Matomo data from the database.', 'content-insights-for-editors'); ?></p>
            <button id="empty_matomo_data" class="button button-secondary"><?php _e('Empty Matomo Data', 'content-insights-for-editors'); ?></button>
            <div id="empty_matomo_data-result"></div>
        </div>
        <?php
    }

    public static function ajax_fetch_matomo_data() {
        check_ajax_referer('cife_fetch_matomo_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'content-insights-for-editors')]);
        }
    
        $matomo = new CIFE_Matomo();
        $result = $matomo->fetch_and_save_matomo_data();
    
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => $result]);
        }
    }

    public static function ajax_empty_matomo_data() {
        check_ajax_referer('cife_fetch_matomo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'content-insights-for-editors')]);
        }

        $matomo = new CIFE_Matomo();
        $result = $matomo->empty_matomo_data();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => __('Matomo data table emptied successfully.', 'content-insights-for-editors')]);
        }
    }
}