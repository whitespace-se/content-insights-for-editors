<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;

class ContentProvider {
  public function getEligiblePostIds($afterId, $limit) {
    $postTypes = Settings::getAnalysedPostTypes();

    $query = new \WP_Query(array(
      'post_type' => $postTypes,
      'post_status' => array('publish'),
      'posts_per_page' => $limit,
      'orderby' => 'ID',
      'order' => 'ASC',
      'fields' => 'ids',
      'no_found_rows' => true,
      'cache_results' => false,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => false,
      'date_query' => array(),
      'post__not_in' => array(),
      'suppress_filters' => false,
      'cife_after_post_id' => (int) $afterId,
    ));

    return array_map('intval', $query->posts);
  }

  public function addAfterIdClause($where, $query) {
    $afterId = (int) $query->get('cife_after_post_id');
    if ($afterId < 1) {
      return $where;
    }

    global $wpdb;
    return $where . $wpdb->prepare(" AND {$wpdb->posts}.ID > %d", $afterId);
  }

  public function getFragments($postId) {
    $post = get_post($postId);
    if (!$post) {
      return array();
    }

    $fragments = array($post->post_content);
    $moduleIds = $this->getModularityModuleIds($postId);

    foreach ($moduleIds as $moduleId) {
      $module = get_post($moduleId);
      if ($module && is_string($module->post_content)) {
        $fragments[] = $module->post_content;
      }

      $rowCount = (int) get_post_meta($moduleId, 'data', true);
      for ($index = 0; $index < $rowCount; $index++) {
        $content = get_post_meta(
          $moduleId,
          'data_' . $index . '_post_content',
          true
        );
        if (is_string($content) && $content !== '') {
          $fragments[] = $content;
        }
      }
    }

    return apply_filters(
      'cife_broken_links_content_fragments',
      $fragments,
      $postId
    );
  }

  private function getModularityModuleIds($postId) {
    $configuration = get_post_meta($postId, 'modularity-modules', true);
    $ids = array();
    $this->collectModuleIds($configuration, $ids);
    return array_values(array_unique(array_map('intval', $ids)));
  }

  private function collectModuleIds($value, array &$ids) {
    if (!is_array($value)) {
      return;
    }

    foreach ($value as $key => $item) {
      if ($key === 'postid' && is_numeric($item)) {
        $ids[] = (int) $item;
      } elseif (is_array($item)) {
        $this->collectModuleIds($item, $ids);
      }
    }
  }
}
