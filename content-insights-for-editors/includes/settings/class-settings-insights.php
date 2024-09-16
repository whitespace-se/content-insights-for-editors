<?php
class CIFE_Settings_Insights {
    public static function init() {
        add_action('load-settings_page_content-insights-settings', [__CLASS__, 'add_screen_options']);
        add_filter('set-screen-option', [__CLASS__, 'save_screen_options'], 10, 3);
        add_filter('manage_settings_page_content-insights-settings_columns', [__CLASS__, 'get_columns']);
    }

    public static function add_screen_options() {
        $screen = get_current_screen();
        if (!is_object($screen) || $screen->id != 'settings_page_content-insights-settings') {
            return;
        }

        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        if ($tab === 'insights') {
            $option = 'per_page';
            $args = [
                'label' => __('Number of items per page:', 'content-insights-for-editors'),
                'default' => 20,
                'option' => 'cife_insights_per_page'
            ];
            add_screen_option($option, $args);

            $columns = self::get_columns();
            $screen->add_option('columns', array('columns' => $columns));
        }
    }

    public static function save_screen_options($status, $option, $value) {
        if ('cife_insights_per_page' == $option) {
            return $value;
        }
        return $status;
    }

    public static function get_columns() {
        return array(
            'domain' => __('Domain', 'content-insights-for-editors'),
            'url_path' => __('URL Path', 'content-insights-for-editors'),
            'day_visitors' => __('Daily Visitors', 'content-insights-for-editors'),
            'day_pageviews' => __('Daily Views', 'content-insights-for-editors'),
            'week_visitors' => __('Weekly Visitors', 'content-insights-for-editors'),
            'week_pageviews' => __('Weekly Views', 'content-insights-for-editors'),
            'month_visitors' => __('Monthly Visitors', 'content-insights-for-editors'),
            'month_pageviews' => __('Monthly Views', 'content-insights-for-editors'),
            'year_visitors' => __('Yearly Visitors', 'content-insights-for-editors'),
            'year_pageviews' => __('Yearly Views', 'content-insights-for-editors'),
            'updated_date' => __('Last Updated', 'content-insights-for-editors'),
        );
    }

    public static function render() {
        require_once plugin_dir_path(__FILE__) . '../class-content-insights-table.php';
        $insights_table = new CIFE_Content_Insights_Table();
        $insights_table->prepare_items();
        ?>
        <div class="wrap">
            <h2><?php _e('Content Insights', 'content-insights-for-editors'); ?></h2>
            <form method="get">
                <input type="hidden" name="page" value="content-insights-settings">
                <input type="hidden" name="tab" value="insights">
                <?php
                $insights_table->search_box('Search', 'search_id');
                $insights_table->display();
                ?>
            </form>
        </div>
        <?php
    }
}