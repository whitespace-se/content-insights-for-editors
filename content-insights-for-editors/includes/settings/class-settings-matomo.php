<?php
class CIFE_Settings_Matomo {
    public static function init() {
        add_settings_section(
            'cife_matomo_section',
            __('Matomo Settings', 'content-insights-for-editors'),
            array(__CLASS__, 'matomo_section_callback'),
            'cife_matomo_settings'
        );

        add_settings_field(
            'matomo_api_url',
            __('Matomo API URL', 'content-insights-for-editors'),
            array(__CLASS__, 'matomo_api_url_callback'),
            'cife_matomo_settings',
            'cife_matomo_section'
        );

        add_settings_field(
            'matomo_id_site',
            __('Matomo Site ID', 'content-insights-for-editors'),
            array(__CLASS__, 'matomo_id_site_callback'),
            'cife_matomo_settings',
            'cife_matomo_section'
        );

        add_settings_field(
            'matomo_api_key',
            __('Matomo API Key', 'content-insights-for-editors'),
            array(__CLASS__, 'matomo_api_key_callback'),
            'cife_matomo_settings',
            'cife_matomo_section'
        );

        add_settings_field(
            'cife_filter_query_vars',
            __('Filter Query Variables', 'content-insights-for-editors'),
            array(__CLASS__, 'render_filter_query_vars_field'),
            'cife_matomo_settings',
            'cife_matomo_section'
        );
    
        register_setting('cife_matomo_settings', 'matomo_api_url');
        register_setting('cife_matomo_settings', 'matomo_id_site');
        register_setting('cife_matomo_settings', 'matomo_api_key');
        register_setting('cife_matomo_settings', 'cife_filter_query_vars');
    }

    public static function render() {
        ?>
        <div class="wrap">
            <div class="content-insights-settings-header">
                <div class="content-insights-settings-title-section">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('cife_matomo_settings');
                do_settings_sections('cife_matomo_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function render_filter_query_vars_field() {
        $filter_query_vars = get_option('cife_filter_query_vars', '0');
        ?>
        <input type="checkbox" id="cife_filter_query_vars" name="cife_filter_query_vars" value="1" <?php checked('1', $filter_query_vars); ?>>
        <label for="cife_filter_query_vars"><?php _e('Remove query variables from URLs', 'content-insights-for-editors'); ?></label>
        <p class="description"><?php _e('If checked, URLs with the same path but different query strings will be merged.', 'content-insights-for-editors'); ?></p>
        <?php
    }

    public static function matomo_section_callback() {
        echo '<p>' . __('Enter your Matomo API settings here.', 'content-insights-for-editors') . '</p>';
    }

    public static function matomo_api_url_callback() {
        $matomo_api_url = get_option('matomo_api_url');
        echo '<input type="text" id="matomo_api_url" name="matomo_api_url" value="' . esc_attr($matomo_api_url) . '" class="regular-text" />';
    }

    public static function matomo_id_site_callback() {
        $matomo_id_site = get_option('matomo_id_site');
        echo '<input type="text" id="matomo_id_site" name="matomo_id_site" value="' . esc_attr($matomo_id_site) . '" class="regular-text" />';
    }

    public static function matomo_api_key_callback() {
        $matomo_api_key = get_option('matomo_api_key');
        echo '<input type="password" id="matomo_api_key" name="matomo_api_key" value="' . esc_attr($matomo_api_key) . '" class="regular-text" />';
    }
}