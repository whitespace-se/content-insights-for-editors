<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;

use CONTENT_INSIGHTS_FOR_EDITORS\Util\PostQuery;
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

    echo '<table>';
    echo $this->metaboxRow(
      __('No. of errors', 'content-insights-for-editors'),
      $postData->length
    );
    if ($postData->length > 0) {
      $linkData = PostQuery::getBrokenLinks($post->ID);
      foreach ($linkData as $link) {
        $data .= "<a href='$link->url' target='_blank'>$link->url</a><br/>";
      }
      echo $this->metaboxRow(
        __('Broken links', 'content-insights-for-editors'),
        $data
      );
    }
    echo $this->metaboxRow(
      sprintf(
        '%s (%d %s)',
        __('Visitors', 'content-insights-for-editors'),
        7,
        _n('day', 'days', 7, 'content-insights-for-editors')
      ),
      $postData->week_visitors
    );
    echo $this->metaboxRow(
      sprintf(
        '%s (%d %s)',
        __('Pageviews', 'content-insights-for-editors'),
        7,
        _n('day', 'days', 7, 'content-insights-for-editors')
      ),
      $postData->week_pageviews
    );
    echo $this->metaboxRow(
      sprintf(
        '%s (%d %s)',
        __('Visitors', 'content-insights-for-editors'),
        30,
        _n('day', 'days', 30, 'content-insights-for-editors')
      ),
      $postData->month_visitors
    );
    echo $this->metaboxRow(
      sprintf(
        '%s (%d %s)',
        __('Pageviews', 'content-insights-for-editors'),
        30,
        _n('day', 'days', 30, 'content-insights-for-editors')
      ),
      $postData->month_pageviews
    );
    echo $this->metaboxRow(
      __('Statistics from', 'content-insights-for-editors'),
      $postData->updated_date
    );
    echo '</table>';
  }

  private function metaboxRow($label, $value) {
    return "<tr>
                    <td valign='top'><b>$label:</b></td><td valign='top'>$value</td>
                </tr>";
  }
}
