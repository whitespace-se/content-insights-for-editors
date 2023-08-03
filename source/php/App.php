<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS;

class App {
  public static $pluginTitle = 'Content Insights for Editors';
  public static $bldTable = 'broken_links_detector';

  public static $installChecked = false;
  public static $wpdb = null;
  public static $nextScheduledRun = null;

  public static $externalDetector = false;

  public function __construct() {
    new Admin\Main();
    new Admin\Settings();
    new Admin\Metabox();
    new Admin\Dashboard();
    new Admin\BrokenLinkCron();
    new Util\Matomo();
    new Util\Plugins\CustomerFeedback();

    add_action('admin_enqueue_scripts', array($this, 'enqueueStyles'));

    global $wpdb;
    self::$wpdb = $wpdb;

    $this->checkInstall();

    //self::$bldTable = $wpdb->prefix . self::$bldTable;

    add_action('admin_menu', array($this, 'addListTablePage'));

    add_action('admin_enqueue_scripts', array($this, 'enqueueStyles'));
    add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));

    add_filter('wp_insert_post_data', array($this, 'checkSavedPost'), 10, 2);

    add_action('wp', array($this, 'postTypeColumns'));
    add_action('before_delete_post', array($this, 'deleteBrokenLinks'));
    add_action('post_submitbox_misc_actions', array($this, 'rescanPost'), 100);

    $this->brokenLinksColumnSorting();

    self::$externalDetector = new ExternalDetector();
    new Editor();

    $offset = get_option('gmt_offset');
    if ($offset > -1) {
      $offset = '+' . $offset;
    } else {
      $offset = '-' . 1 * abs($offset);
    }
    self::$nextScheduledRun = date(
        'Y-m-d H:i',
        strtotime(
          $offset . ' hours',
          wp_next_scheduled('broken-links-detector-external')
        )
      );
  }

  public function enqueueStyles() {
    wp_enqueue_style(
      'cife_css',
      plugins_url('../css/content-insights-for-editors.css', __FILE__)
    );
  }

  public static function checkInstall()
    {
        if (self::$installChecked) {
            return;
        }

        $tableName = self::$bldTable;

        //Update to 1.0.1
        if(get_site_option('broken-links-detector-db-version') == "1.0.0") {

            $charsetCollation = self::$wpdb->get_charset_collate();
            $tableName = self::$bldTable;

            $sql = "ALTER TABLE $tableName
                    ADD time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            update_option('broken-links-detector-db-version', '1.0.1');
        }

        if(get_site_option('broken-links-detector-db-version')) {
            return true; 
        }

        //Install
        if(!self::$wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName) {
            self::install();
        }
    }

    public function rescanPost()
    {
        echo '<div class="misc-pub-section">
            <label style="display:block;margin-bottom:5px;"><input type="checkbox" name="broken-link-detector-rescan" value="true"> ' . __('Rescan broken links', 'broken-link-detector') . '</label>
            <small>' . __('The rescan will be executed for this post only. The scan will execute direcly after the save is completed and may take a few minutes to complete.', 'broken-links-detector') . '</small>
        </div>';
    }

    /**
     * Sort post if sorting on broken-links column
     * @return void
     */
    public function brokenLinksColumnSorting()
    {
        add_filter('posts_fields', function ($fields, $query) {
            if ($query->get('orderby') !== 'broken-links') {
                return $fields;
            }

            global $wpdb;

            $fields .= ", (
                SELECT COUNT(*)
                FROM " . self::$bldTable . "
                WHERE post_id = {$wpdb->posts}.ID
            ) AS broken_links_count";

            return $fields;
        }, 10, 2);

        add_filter('posts_orderby', function ($orderby, $query) {
            if ($query->get('orderby') !== 'broken-links') {
                return $orderby;
            }

            $orderby = "broken_links_count {$query->get('order')}, " . $orderby;
            return $orderby;
        }, 10, 2);
    }

    /**
     * Add broken links column to post type post list table
     * @return void
     */
    public function postTypeColumns()
    {
        $postTypes = get_post_types();

        foreach ($postTypes as $postType) {
            add_filter('manage_' . $postType . '_posts_columns', function ($columns) {
                broken_link_detector_array_splice_assoc($columns, -1, 0, array(
                    'broken-links' => __('Broken links', 'broken-links-detector')
                ));

                return $columns;
            }, 50);

            add_filter('manage_edit-' . $postType . '_sortable_columns', function ($columns) {
                $columns['broken-links'] = 'broken-links';
                return $columns;
            }, 50);

            add_action('manage_' . $postType . '_posts_custom_column', function ($column, $postId) {
                if ($column !== 'broken-links') {
                    return;
                }

                $links = ListTable::getBrokenLinksCount($postId);

                if ($links > 0) {
                    echo '<span class="broken-link-detector-label">' . $links . '</span>';
                } else {
                    echo '<span aria-hidden="true">—</span>';
                }
            }, 20, 2);
        }
    }

    /**
     * Adds the list table page of broken links
     */
    public function addListTablePage()
    {
        add_submenu_page(
            'options-general.php',
            'Broken links',
            'Broken links',
            'edit_posts',
            'broken-links-detector',
            function () {
                checkInstall();

                $listTable = new ListTable();

                $offset = get_option('gmt_offset');

                if ($offset > -1) {
                    $offset = '+' . $offset;
                } else {
                    $offset = '-' . (1 * abs($offset));
                }

                $nextRun = date('Y-m-d H:i', strtotime($offset . ' hours', wp_next_scheduled('broken-links-detector-external')));

                include BROKENLINKDETECTOR_TEMPLATE_PATH . 'list-table.php';
            }
        );
    }

    /**
     * Setsup the database table on plugin activation (hooked in App.php)
     * @return void
     */
    public static function install()
    {
        $charsetCollation = self::$wpdb->get_charset_collate();
        $tableName = self::$bldTable;

        if (!empty(get_site_option('broken-links-detector-db-version')) && self::$wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName) {
            return;
        }

        $sql = "CREATE TABLE $tableName (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            url varchar(255) DEFAULT '' NOT NULL,
            time timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY id (id)
        ) $charsetCollation;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option('broken-links-detector-db-version', '1.0.1');
    }

    /**
     * Drops the database table on plugin deactivation (hooked in App.php)
     * @return void
     */
    public static function uninstall()
    {
        $tableName = self::$bldTable;
        $sql = 'DROP TABLE ' . $tableName;

        self::$wpdb->query($sql);

        delete_option('broken-links-detector-db-version');
    }

    /**
     * Checks if a saved posts permalink is changed and updates permalinks throughout the site
     * @param  array $data     Post data
     * @param  array $postarr  $_POST data
     * @return array           Post data (do not change)
     */
    public function checkSavedPost($data, $postarr)
    {
        remove_action('wp_insert_post_data', array($this, 'checkSavedPost'), 10, 2);

        $detector = new InternalDetector($data, $postarr);
        return $data;
    }

    /**
     * Remove broken links when deleting a page
     * @param int $postId The post id that is being deleted
     */
    public function deleteBrokenLinks($postId)
    {
        global $wpdb;
        $tableName = self::$bldTable;
        $wpdb->delete($tableName, array('post_id' => $postId), array('%d'));
    }

    /**
     * Enqueue required scripts
     * @return void
     */
    public function enqueueScripts()
    {
    }
}
