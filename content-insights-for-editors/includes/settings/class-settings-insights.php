<?php
class CIFE_Settings_Insights {
    public static function init() {
        // No initialization needed for this tab
    }

    public static function render() {
        require_once plugin_dir_path(__FILE__) . '../class-content-insights-table.php';
        $insights_table = new CIFE_Content_Insights_Table();
        $insights_table->prepare_items();
        ?>
        <div class="wrap">
            <h2><?php _e('Content Insights', 'content-insights-for-editors'); ?></h2>
            <form method="post">
                <input type="hidden" name="page" value="content-insights-settings">
                <?php
                $insights_table->search_box('Search', 'search_id');
                $insights_table->display();
                ?>
            </form>
        </div>
        <?php
    }
}