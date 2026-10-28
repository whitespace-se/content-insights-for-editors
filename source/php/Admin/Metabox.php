<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;

use CONTENT_INSIGHTS_FOR_EDITORS\Util\PostQuery;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\Matomo;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkRepository;
use CONTENT_INSIGHTS_FOR_EDITORS\App;

class Metabox {
  function __construct() {
    add_action('add_meta_boxes', array($this, 'addMetaBox'));
  }

  public function addMetaBox() {
    $postTypes = Settings::getSelectedPostTypes();
    add_meta_box(
      'CONTENT_INSIGHTS_FOR_EDITORS',
      App::$pluginTitle,
      array($this, 'metaBoxMarkup'),
      $postTypes,
      'side',
      'default',
      null
    );
  }

  public function metaBoxMarkup($post) {
    $postData = PostQuery::getListPosts(false, false, $post->ID);

    if (!is_array($postData) || count($postData) < 1) {
      echo '<div>';
      echo __('No data available', 'content-insights-for-editors');
      echo '</div>';
      return;
    }
    $postData = $postData[0];

    echo '<table class="cife-metabox-table">';
    echo $this->metaboxRow(
      __('Broken internal links', 'content-insights-for-editors'),
      $postData->length
    );
    $externalCount = (new ExternalLinkRepository())->countForPost($post->ID);
    if ($externalCount > 0) {
      $externalUrl = add_query_arg(
        array('page' => ExternalLinks::PAGE_SLUG, 'post_id' => (int) $post->ID),
        admin_url('admin.php')
      );
      echo $this->metaboxLinkRow(
        __('External links', 'content-insights-for-editors'),
        /* translators: %d: number of external links found on the current page. */
        sprintf(_n('%d link', '%d links', $externalCount, 'content-insights-for-editors'), $externalCount),
        $externalUrl
      );
    } else {
      echo $this->metaboxRow(__('External links', 'content-insights-for-editors'), 0);
    }

    if (!Matomo::$matomoIsActive) {
      echo $this->metaboxRow(
        __('Visitor statistics', 'content-insights-for-editors'),
        __('Matomo is not connected', 'content-insights-for-editors')
      );
      echo '</table>';
      return;
    }

    echo $this->metaboxRow(
      sprintf('%s (%d %s)', __('Visitors', 'content-insights-for-editors'), 7, _n('day', 'days', 7, 'content-insights-for-editors')),
      $this->formatMetric($postData->week_visitors)
    );
    echo $this->metaboxRow(
      sprintf('%s (%d %s)', __('Pageviews', 'content-insights-for-editors'), 7, _n('day', 'days', 7, 'content-insights-for-editors')),
      $this->formatMetric($postData->week_pageviews)
    );
    echo $this->metaboxRow(
      sprintf('%s (%d %s)', __('Visitors', 'content-insights-for-editors'), 30, _n('day', 'days', 30, 'content-insights-for-editors')),
      $this->formatMetric($postData->month_visitors)
    );
    echo $this->metaboxRow(
      sprintf('%s (%d %s)', __('Pageviews', 'content-insights-for-editors'), 30, _n('day', 'days', 30, 'content-insights-for-editors')),
      $this->formatMetric($postData->month_pageviews)
    );
    echo $this->metaboxRow(
      __('Statistics from', 'content-insights-for-editors'),
      $postData->updated_date ?: __('No data yet', 'content-insights-for-editors')
    );
    echo '</table>';
  }

  private function metaboxRow($label, $value) {
    return sprintf(
      '<tr><td><b>%s:</b></td><td>%s</td></tr>',
      esc_html($label),
      esc_html($value ?? '')
    );
  }

  private function metaboxLinkRow($label, $value, $url) {
    return sprintf(
      '<tr><td><b>%s:</b></td><td><a href="%s">%s</a></td></tr>',
      esc_html($label),
      esc_url($url),
      esc_html($value)
    );
  }

  private function formatMetric($value) {
    return is_numeric($value) ? number_format_i18n((float) $value) : __('No data yet', 'content-insights-for-editors');
  }
}
