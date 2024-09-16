<?php
class CIFE_Settings_Base {
    protected static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    public function add_plugin_page() {
        add_options_page(
            'Content Insights Settings',
            'Content Insights',
            'manage_options',
            'content-insights-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        $tabs = array(
            'general' => __('General', 'content-insights-for-editors'),
            'matomo' => __('Matomo', 'content-insights-for-editors'),
            'feedback' => __('Feedback', 'content-insights-for-editors'),
            'fetch' => __('Fetch Matomo Data', 'content-insights-for-editors'),
            'insights' => __('Content Insights', 'content-insights-for-editors')
        );
        ?>
        <div class="wrap">
            <h1><?php _e('Content Insights Settings', 'content-insights-for-editors'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php
                foreach ($tabs as $tab => $name) {
                    $class = ($tab === $active_tab) ? 'nav-tab-active' : '';
                    echo '<a href="?page=content-insights-settings&tab=' . $tab . '" class="nav-tab ' . $class . '">' . $name . '</a>';
                }
                ?>
            </h2>

            <?php
            if ($active_tab === 'fetch') {
                CIFE_Settings_Fetch_Data::render();
            } elseif ($active_tab === 'insights') {
                CIFE_Settings_Insights::render();
            } else {
                $class_name = 'CIFE_Settings_' . ucfirst($active_tab);
                if (class_exists($class_name) && method_exists($class_name, 'render')) {
                    $class_name::render();
                } else {
                    _e('Settings not found for this tab.', 'content-insights-for-editors');
                }
            }
            ?>
        </div>
        <?php
    }

    public function page_init() {
        $tabs = array('General', 'Matomo', 'Feedback', 'Fetch_Data', 'Insights');
        foreach ($tabs as $tab) {
            $class_name = 'CIFE_Settings_' . $tab;
            if (class_exists($class_name) && method_exists($class_name, 'init')) {
                $class_name::init();
            }
        }
    }
}