<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;

class Matomo {
  private static $dbTable = 'content_insights_for_editors';
  private static $wpdb = null;

  public static $installChecked = false;
  public static $matomoIsActive = false;

  private $matomoUrl = null;
  private $idSite = null;

  private $parameters = array();

  public function __construct() {
    global $wpdb;
    self::$wpdb = $wpdb;

    self::checkInstall();

    $this->matomoUrl = Settings::getMatomoApiUrl();

    $this->parameters['module'] = 'API';
    $this->parameters['format'] = 'JSON';

    $this->parameters['idSite'] = Settings::getMatomoIdSite();

    $this->parameters['token_auth'] = Settings::getMatomoApiToken();
    self::$matomoIsActive = $this->checkMatomoParamtersSet();
    if (!self::$matomoIsActive) {
      add_action('admin_notices', array($this, 'adminNoticeWarnMatomo'));
    }

    add_action('wp', array($this, 'schedule'));
    add_action('content-insights-for-editors-matomo', array(
      $this,
      'updatePagesWeekAndMonth',
    ));
  }

  private function checkMatomoParamtersSet() {
    if (empty($this->matomoUrl)) {
      return false;
    }
    if (empty($this->parameters['idSite'])) {
      return false;
    }
    if (empty($this->parameters['token_auth'])) {
      return false;
    }
    return true;
  }

  public function adminNoticeWarnMatomo() {
    ?>
    <div class="notice notice-warning">
      <p><?php _e(
        'Matomo is not configured',
        'content-insights-for-editors'
      ); ?></p>
      <a href="<?php echo admin_url(
        'admin.php?page=content-insights-for-editors-page-settings'
      ); ?>"><?php _e(
  'Configure settings for Matomo',
  'content-insights-for-editors'
); ?></a>
    </div>
    <?php
  }

  public function schedule() {
    if (wp_next_scheduled('content-insights-for-editors-matomo')) {
      return;
    }

    wp_schedule_event(time(), 'daily', 'content-insights-for-editors-matomo');
  }

  public static function getDbTable() {
    return self::$wpdb->prefix . self::$dbTable;
  }

  private function truncateTable() {
    $tableName = self::getDbTable();
    self::$wpdb->query("TRUNCATE TABLE $tableName");
  }

  public function updatePagesWeekAndMonth() {
    $this->truncateTable();
    $pages = $this->getPagesWeekAndMonth();

    foreach ($pages as $page) {
      $this->upsert($page);
    }
  }

  public function getPagesWeekAndMonth() {
    $this->parameters['method'] = 'Actions.getPageUrls';
    $this->parameters['period'] = 'range';
    $this->parameters['expanded'] = 0;
    $this->parameters['flat'] = 1;
    $this->parameters['filter_limit'] = 1000;

    $this->parameters['date'] = $this->getDateRangeStr(7);

    $week_result = $this->requestPager();

    $this->parameters['date'] = $this->getDateRangeStr(30);

    $month_result = $this->requestPager();

    $result = array();
    $this->aggregateWeekAndMonth($result, $week_result, 'week');
    $this->aggregateWeekAndMonth($result, $month_result, 'month');

    $return_data = array();
    foreach ($result as $url_path => $result_data) {
      $post_id =
        $url_path == '/'
          ? get_option('page_on_front')
          : url_to_postid($url_path);

      $result_data->post_id = $post_id;

      // Only urls with post ids
      if ($post_id !== 0) {
        if (empty($return_data[$post_id])) {
          $return_data[$post_id] = $result_data;

          if (empty($return_data[$post_id]->week_visitors)) {
            $return_data[$post_id]->week_visitors = 0;
          }
          if (empty($return_data[$post_id]->week_pageviews)) {
            $return_data[$post_id]->week_pageviews = 0;
          }
        } else {
          $return_data[$post_id]->week_visitors += $result_data->week_visitors;
          $return_data[$post_id]->week_pageviews +=
            $result_data->week_pageviews;
          $return_data[$post_id]->month_visitors +=
            $result_data->month_visitors;
          $return_data[$post_id]->month_pageviews +=
            $result_data->month_pageviews;
        }
      }
    }

    usort($return_data, function ($b, $a) {
      $retval = $a->week_pageviews <=> $b->week_pageviews;
      if ($retval == 0) {
        $retval = $a->month_pageviews <=> $b->month_pageviews;
        if ($retval == 0) {
          $retval = $a->month_visitors <=> $b->month_visitors;
        }
      }
      return $retval;
    });

    return $return_data;
  }

  private function aggregateWeekAndMonth(&$result, $values, $prefix) {
    if(!empty($values)) {
      foreach ($values as $value) {
        if (array_key_exists($value['label'], $result)) {
          $result[$value['label']] = (object) array_merge(
            (array) $result[$value['label']],
            [
              $prefix . '_visitors' => $value['nb_visits'],
              $prefix . '_pageviews' => $value['nb_hits'],
            ]
          );
          continue;
        }

        $result[$value['label']] = (object) [
          'url_path' => $value['label'],
          $prefix . '_visitors' => $value['nb_visits'],
          $prefix . '_pageviews' => $value['nb_hits'],
        ];
      }
    }
  }

  private function getDateRangeStr($days) {
    return date('Y-m-d', strtotime("-$days days")) . ',' . date('Y-m-d');
  }

  private function requestPager() {
    $filter = array();
    $filter['filter_offset'] = 0;

    $pager = true;
    $i = 1;
    $data = array();
    while ($pager) {
      $response = $this->request($filter);
      if (empty($response)) {
        $pager = false;
      }
      $data = is_array($response) ? array_merge($data, $response) : $data;
      $filter['filter_offset'] = $this->parameters['filter_limit'] * $i++;
    }
    return $data;
  }

  private function request($filter) {
    $query = array_merge($this->parameters, $filter);

    $queryString = urldecode(http_build_query($query));

    $url = $this->matomoUrl . '?' . $queryString;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);

    return json_decode($result, true);
  }

  private function upsert($item) {
    $tableName = self::getDbTable();
    $sql = "INSERT INTO $tableName (post_id, url_path, week_visitors, week_pageviews, month_visitors, month_pageviews) 
                    VALUES (%d, %s, %d, %d, %d, %d) 
                    ON DUPLICATE KEY UPDATE 
                        url_path = %s, 
                        week_visitors = %d, 
                        week_pageviews = %d, 
                        month_visitors = %d, 
                        month_pageviews = %d,
                        updated_date = %s";

    $sql = self::$wpdb->prepare(
      $sql,
      $item->post_id,
      $item->url_path,
      $item->week_visitors ?? 0,
      $item->week_pageviews ?? 0,
      $item->month_visitors ?? 0,
      $item->month_pageviews ?? 0,
      $item->url_path,
      $item->week_visitors ?? 0,
      $item->week_pageviews ?? 0,
      $item->month_visitors ?? 0,
      $item->month_pageviews ?? 0,
      date('Y-m-d H:i:s')
    );

    self::$wpdb->query($sql);
  }

  public static function checkInstall() {
    if (self::$installChecked) {
      return;
    }
    self::install();
    self::$installChecked = true;
  }

  public static function install() {
    $charsetCollation = self::$wpdb->get_charset_collate();
    $tableName = self::getDbTable();

    if (self::$wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName) {
      return;
    }

    $sql = "CREATE TABLE $tableName (
            id INT NOT NULL AUTO_INCREMENT,
            post_id INT DEFAULT NULL,
            url_path varchar(255) DEFAULT '' NOT NULL,
            week_visitors INT DEFAULT 0,
            week_pageviews INT DEFAULT 0,
            month_visitors INT DEFAULT 0,
            month_pageviews INT DEFAULT 0,
            updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (post_id)
        ) $charsetCollation;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }
}
