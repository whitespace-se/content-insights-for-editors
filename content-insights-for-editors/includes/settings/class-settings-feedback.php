<?php
class CIFE_Settings_Feedback {
    public static function init() {
        add_settings_section(
            'cife_feedback_section',
            __('Feedback Settings', 'content-insights-for-editors'),
            array(__CLASS__, 'feedback_section_callback'),
            'cife_feedback_settings'
        );

        add_settings_field(
            'cife_feedback_email',
            __('Feedback Email', 'content-insights-for-editors'),
            array(__CLASS__, 'feedback_email_callback'),
            'cife_feedback_settings',
            'cife_feedback_section'
        );

        register_setting('cife_feedback_settings', 'cife_feedback_email');
    }

    public static function render() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('cife_feedback_settings');
            do_settings_sections('cife_feedback_settings');
            submit_button();
            ?>
        </form>
        <?php
    }

    public static function feedback_section_callback() {
        echo '<p>' . __('Configure feedback settings for Content Insights.', 'content-insights-for-editors') . '</p>';
    }

    public static function feedback_email_callback() {
        $feedback_email = get_option('cife_feedback_email');
        echo '<input type="email" id="cife_feedback_email" name="cife_feedback_email" value="' . esc_attr($feedback_email) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter the email address where feedback should be sent.', 'content-insights-for-editors') . '</p>';
    }
}