<?php
class CIFE_Settings_General {
    public static function init() {
        add_settings_section(
            'cife_general_section',
            __('General Settings', 'content-insights-for-editors'),
            array(__CLASS__, 'general_section_callback'),
            'cife_general_settings'
        );

        add_settings_field(
            'cife_enable_plugin',
            __('Enable Plugin', 'content-insights-for-editors'),
            array(__CLASS__, 'enable_plugin_callback'),
            'cife_general_settings',
            'cife_general_section'
        );

        register_setting('cife_general_settings', 'cife_enable_plugin');
    }

    public static function render() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('cife_general_settings');
            do_settings_sections('cife_general_settings');
            submit_button();
            ?>
        </form>
        <?php
    }

    public static function general_section_callback() {
        echo '<p>' . __('General settings for Content Insights for Editors.', 'content-insights-for-editors') . '</p>';
    }

    public static function enable_plugin_callback() {
        $enable_plugin = get_option('cife_enable_plugin', '1');
        echo '<input type="checkbox" id="cife_enable_plugin" name="cife_enable_plugin" value="1" ' . checked('1', $enable_plugin, false) . '/>';
        echo '<label for="cife_enable_plugin">' . __('Enable Content Insights for Editors', 'content-insights-for-editors') . '</label>';
    }
}