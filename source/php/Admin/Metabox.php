<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;

use CONTENT_INSIGHTS_FOR_EDITORS\Util\PostQuery;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\Matomo;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkRepository;
use CONTENT_INSIGHTS_FOR_EDITORS\Performance\Analyzer as PerformanceAnalyzer;
use CONTENT_INSIGHTS_FOR_EDITORS\App;

class Metabox {
  function __construct() {
    add_action('add_meta_boxes', array($this, 'addMetaBox'));
  }

  public function addMetaBox() {
    $postTypes = Settings::getAnalysedPostTypes();
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
    $postData = is_array($postData) && count($postData) > 0
      ? $postData[0]
      : null;

    echo '<table class="cife-metabox-table">';
    echo $this->metaboxRow(
      __('Broken internal links', 'content-insights-for-editors'),
      $postData ? $postData->length : __('No data available', 'content-insights-for-editors')
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
    } else {
      echo $this->metaboxRow(
        sprintf('%s (%d %s)', __('Visitors', 'content-insights-for-editors'), 7, _n('day', 'days', 7, 'content-insights-for-editors')),
        $this->formatMetric($postData ? $postData->week_visitors : null)
      );
      echo $this->metaboxRow(
        sprintf('%s (%d %s)', __('Pageviews', 'content-insights-for-editors'), 7, _n('day', 'days', 7, 'content-insights-for-editors')),
        $this->formatMetric($postData ? $postData->week_pageviews : null)
      );
      echo $this->metaboxRow(
        sprintf('%s (%d %s)', __('Visitors', 'content-insights-for-editors'), 30, _n('day', 'days', 30, 'content-insights-for-editors')),
        $this->formatMetric($postData ? $postData->month_visitors : null)
      );
      echo $this->metaboxRow(
        sprintf('%s (%d %s)', __('Pageviews', 'content-insights-for-editors'), 30, _n('day', 'days', 30, 'content-insights-for-editors')),
        $this->formatMetric($postData ? $postData->month_pageviews : null)
      );
      echo $this->metaboxRow(
        __('Statistics from', 'content-insights-for-editors'),
        $postData && $postData->updated_date
          ? $postData->updated_date
          : __('No data yet', 'content-insights-for-editors')
      );
    }
    echo '</table>';
    $this->performanceMarkup($post->ID);
  }

  private function performanceMarkup($postId) {
    $metrics = (new PerformanceAnalyzer())->getMetrics($postId);
    $riskLabels = array(
      'low' => __('Low', 'content-insights-for-editors'),
      'moderate' => __('Moderate', 'content-insights-for-editors'),
      'high' => __('High', 'content-insights-for-editors'),
    );

    echo '<div class="cife-performance">';
    echo '<div class="cife-performance__heading"><h3>' .
      esc_html__('Page performance risk', 'content-insights-for-editors') .
      '</h3><span class="cife-performance__badge cife-performance__badge--' .
      esc_attr($metrics['risk']) . '">' .
      esc_html($riskLabels[$metrics['risk']]) . '</span></div>';
    echo '<p class="description">' . esc_html__(
      'Content-based estimate—not a measured load time.',
      'content-insights-for-editors'
    ) . '</p>';
    echo '<dl class="cife-performance__metrics">';
    echo $this->performanceMetric(
      __('Content markup', 'content-insights-for-editors'),
      size_format($metrics['content_bytes'], 1)
    );
    echo $this->performanceMetric(
      __('Page elements', 'content-insights-for-editors'),
      number_format_i18n($metrics['elements'])
    );
    echo $this->performanceMetric(
      __('Referenced media', 'content-insights-for-editors'),
      sprintf(
        /* translators: 1: number of media files, 2: combined source size. */
        __('%1$d files / %2$s', 'content-insights-for-editors'),
        $metrics['media_count'],
        size_format($metrics['media_bytes'], 1)
      )
    );
    echo $this->performanceMetric(
      __('Large media files', 'content-insights-for-editors'),
      number_format_i18n($metrics['large_media_count'])
    );
    echo $this->performanceMetric(
      __('External resources', 'content-insights-for-editors'),
      number_format_i18n($metrics['external_resources'])
    );
    echo $this->performanceMetric(
      __('Embeds', 'content-insights-for-editors'),
      number_format_i18n($metrics['embeds'])
    );
    echo $this->performanceMetric(
      __('External scripts', 'content-insights-for-editors'),
      number_format_i18n($metrics['external_scripts'])
    );
    echo '</dl>';
    echo '<p class="description">' . esc_html__(
      'External links are excluded because they load only when clicked.',
      'content-insights-for-editors'
    ) . '</p>';
    echo '</div>';
  }

  private function performanceMetric($label, $value) {
    return sprintf(
      '<div><dt>%s</dt><dd>%s</dd></div>',
      esc_html($label),
      esc_html($value)
    );
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
