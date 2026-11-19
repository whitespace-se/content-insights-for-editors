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

      foreach ($this->getAcfFragments($moduleId) as $fragment) {
        $fragments[] = $fragment;
      }
    }

    foreach ($this->getAcfFragments($postId) as $fragment) {
      $fragments[] = $fragment;
    }

    return apply_filters(
      'cife_broken_links_content_fragments',
      $fragments,
      $postId
    );
  }

  private function getAcfFragments($postId) {
    if (!function_exists('get_field_objects')) {
      return array();
    }

    $fields = get_field_objects($postId, true, true);
    if (!is_array($fields)) {
      return array();
    }

    $fragments = array();
    foreach ($fields as $field) {
      $this->collectAcfFieldFragments($field, $postId, $fragments);
    }
    return $fragments;
  }

  private function collectAcfFieldFragments($field, $postId, array &$fragments) {
    if (!is_array($field) || empty($field['type'])) {
      return;
    }
    if (!apply_filters('cife_broken_links_scan_acf_field', true, $field, $postId)) {
      return;
    }

    $value = isset($field['value']) ? $field['value'] : null;
    switch ($field['type']) {
      case 'url':
        $this->addUrlFragment($value, $fragments);
        return;
      case 'link':
      case 'file':
        if (is_array($value) && isset($value['url'])) {
          $value = $value['url'];
        } elseif ($field['type'] === 'file' && is_numeric($value) &&
          function_exists('wp_get_attachment_url')) {
          $value = wp_get_attachment_url((int) $value);
        }
        $this->addUrlFragment($value, $fragments);
        return;
      case 'wysiwyg':
        if (is_string($value) && trim($value) !== '') {
          $fragments[] = $value;
        }
        return;
      case 'group':
      case 'clone':
        $this->collectAcfRow($field, $value, $postId, $fragments);
        return;
      case 'repeater':
      case 'flexible_content':
        if (!is_array($value)) {
          return;
        }
        foreach ($value as $row) {
          $this->collectAcfRow($field, $row, $postId, $fragments);
        }
        return;
    }
  }

  private function collectAcfRow($field, $row, $postId, array &$fragments) {
    if (!is_array($row)) {
      return;
    }

    $subFields = isset($field['sub_fields']) && is_array($field['sub_fields'])
      ? $field['sub_fields']
      : array();
    if ($field['type'] === 'flexible_content' && !empty($field['layouts'])) {
      $subFields = array();
      foreach ($field['layouts'] as $layout) {
        if (!is_array($layout) || empty($layout['sub_fields'])) {
          continue;
        }
        if (!empty($row['acf_fc_layout']) && isset($layout['name']) &&
          $layout['name'] !== $row['acf_fc_layout']) {
          continue;
        }
        $subFields = array_merge($subFields, $layout['sub_fields']);
      }
    }

    foreach ($subFields as $subField) {
      if (!is_array($subField) || empty($subField['name'])) {
        continue;
      }
      $subField['value'] = isset($row[$subField['name']])
        ? $row[$subField['name']]
        : null;
      $this->collectAcfFieldFragments($subField, $postId, $fragments);
    }
  }

  private function addUrlFragment($url, array &$fragments) {
    if (!is_string($url) || trim($url) === '') {
      return;
    }
    $fragments[] = '<a href="' . htmlspecialchars(
      trim($url),
      ENT_QUOTES | ENT_HTML5,
      'UTF-8'
    ) . '"></a>';
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
