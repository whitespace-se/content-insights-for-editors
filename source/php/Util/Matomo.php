<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;

class Matomo {
  const CRON_HOOK = 'content-insights-for-editors-matomo';
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

    add_action('init', array($this, 'schedule'));
    add_action(self::CRON_HOOK, array(
      $this,
      'runScheduledUpdate',
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
			<p><?php _e('Matomo is not configured', 'content-insights-for-editors'); ?></p>
			<a href="<?php echo esc_url(admin_url(
     'admin.php?page=content-insights-for-editors-page-settings'
   )); ?>"><?php _e(
  'Configure settings for Matomo',
  'content-insights-for-editors'
); ?></a>
		</div>
		<?php
  }

  public function schedule() {
    if (!self::$matomoIsActive) {
      wp_clear_scheduled_hook(self::CRON_HOOK);
      return;
    }

    if (!wp_next_scheduled(self::CRON_HOOK)) {
      wp_schedule_event(time() + MINUTE_IN_SECONDS, 'daily', self::CRON_HOOK);
    }
  }

  public static function deactivate() {
    wp_clear_scheduled_hook(self::CRON_HOOK);
  }

  public static function getDbTable() {
    return self::$wpdb->prefix . self::$dbTable;
  }

  public function updatePagesWeekAndMonth() {
    $pages = $this->getPagesWeekAndMonth();

    foreach ($pages as $page) {
      $post = !empty($page->post_id) ? get_post($page->post_id) : null;
      if (
        !$post ||
        $post->post_status !== 'publish' ||
        !in_array($post->post_type, Settings::getAnalysedPostTypes(), true)
      ) {
        continue;
      }
      $this->upsert($page);
    }
  }

  public function runScheduledUpdate() {
    try {
      $this->updatePagesWeekAndMonth();
      return true;
    } catch (\Throwable $error) {
      do_action('cife_matomo_update_error', $error);
      return false;
    }
  }

  public function getPagesWeekAndMonth() {
    $this->parameters['method'] = 'Actions.getPageUrls';
    $this->parameters['period'] = 'range';
    $this->parameters['expanded'] = 0;
    $this->parameters['flat'] = 1;
    $this->parameters['filter_limit'] = 2000;

    $this->parameters['date'] = $this->getDateRangeStr(7);

    $week_result = $this->request();

    $this->parameters['date'] = $this->getDateRangeStr(30);

    $month_result = $this->request();

    $result = array();
    $this->aggregateWeekAndMonth($result, $week_result, 'week');
    $this->aggregateWeekAndMonth($result, $month_result, 'month');

    return $result;
  }

  private function aggregateWeekAndMonth(&$result, $values, $prefix) {
    if (!is_array($values)) {
      return;
    }
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

      $post_id =
        $value['label'] == '/'
          ? get_option('page_on_front')
          : url_to_postid($value['label']);

      $result[$value['label']] = (object) [
        'post_id' => $post_id,
        'url_path' => $value['label'],
        $prefix . '_visitors' => $value['nb_visits'],
        $prefix . '_pageviews' => $value['nb_hits'],
      ];
    }
  }

  private function getDateRangeStr($days) {
    $timezone = wp_timezone();
    $end = new \DateTimeImmutable('now', $timezone);
    $start = $end->modify(sprintf('-%d days', absint($days)));
    return $start->format('Y-m-d') . ',' . $end->format('Y-m-d');
  }

  private function request() {
    $url = esc_url_raw($this->matomoUrl);
    if (!$url || !in_array(wp_parse_url($url, PHP_URL_SCHEME), array('http', 'https'), true)) {
      throw new \RuntimeException(
        __('The configured Matomo API URL is invalid.', 'content-insights-for-editors')
      );
    }

    $args = apply_filters(
      'cife_matomo_http_args',
      array(
        'timeout' => 20,
        'redirection' => 3,
        'sslverify' => true,
        'body' => $this->parameters,
        'user-agent' => 'Content Insights for Editors/' . CONTENT_INSIGHTS_FOR_EDITORS_VERSION,
      ),
      $url
    );
    $response = wp_safe_remote_post($url, $args);
    if (is_wp_error($response)) {
      throw new \RuntimeException($response->get_error_message());
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    if ($status < 200 || $status >= 300) {
      throw new \RuntimeException(
        sprintf(
          /* translators: %d: HTTP response status code returned by Matomo. */
          __('Matomo returned HTTP status %d.', 'content-insights-for-editors'),
          $status
        )
      );
    }

    $result = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($result) || json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException(
        __('Matomo returned an invalid response.', 'content-insights-for-editors')
      );
    }
    if (isset($result['result']) && $result['result'] === 'error') {
      throw new \RuntimeException(
        isset($result['message'])
          ? sanitize_text_field($result['message'])
          : __('Matomo returned an error.', 'content-insights-for-editors')
      );
    }

    return $result;
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
      current_time('mysql')
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
