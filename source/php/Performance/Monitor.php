<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Performance;

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;

class Monitor {
  public function __construct() {
    add_action('acf/save_post', array($this, 'refreshAfterSave'), 20);
  }

  public function refreshAfterSave($postId) {
    if (!is_numeric($postId) || wp_is_post_revision($postId)) {
      return;
    }
    $post = get_post((int) $postId);
    if (
      !$post ||
      $post->post_status !== 'publish' ||
      !in_array($post->post_type, Settings::getAnalysedPostTypes(), true)
    ) {
      Analyzer::deleteStored((int) $postId);
      return;
    }
    (new Analyzer())->analyzeAndStore((int) $postId);
  }
}
