<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Performance;

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ContentProvider;

class Analyzer {
  const LARGE_MEDIA_BYTES = 524288;
  const METRICS_META_KEY = '_cife_performance_metrics';
  const RISK_META_KEY = '_cife_performance_risk';
  const CACHE_VERSION = 1;

  public function getMetrics($postId) {
    $post = get_post($postId);
    $cached = get_post_meta($postId, self::METRICS_META_KEY, true);
    $fingerprint = $this->getFingerprint($post);
    if (
      is_array($cached) &&
      isset($cached['_fingerprint']) &&
      hash_equals((string) $cached['_fingerprint'], $fingerprint)
    ) {
      unset($cached['_fingerprint']);
      return $cached;
    }

    return $this->analyzeAndStore($postId);
  }

  public function analyzeAndStore($postId) {
    $metrics = $this->analyze($postId);
    $storedMetrics = $metrics;
    $storedMetrics['_fingerprint'] = $this->getFingerprint(get_post($postId));
    update_post_meta($postId, self::METRICS_META_KEY, $storedMetrics);
    update_post_meta($postId, self::RISK_META_KEY, $metrics['risk']);
    return $metrics;
  }

  public static function deleteStored($postId) {
    delete_post_meta($postId, self::METRICS_META_KEY);
    delete_post_meta($postId, self::RISK_META_KEY);
  }

  public function analyze($postId) {
    $metrics = array(
      'content_bytes' => 0,
      'elements' => 0,
      'media_count' => 0,
      'media_bytes' => 0,
      'large_media_count' => 0,
      'external_resources' => 0,
      'external_scripts' => 0,
      'embeds' => 0,
      'score' => 0,
      'risk' => 'low',
    );
    $attachmentIds = array();
    $externalResources = array();
    $externalScripts = array();
    $embeds = 0;

    $fragments = (new ContentProvider())->getFragments($postId);
    foreach ($fragments as $fragment) {
      if (!is_string($fragment) || trim($fragment) === '') {
        continue;
      }
      $metrics['content_bytes'] += strlen($fragment);
      $this->inspectHtml(
        $fragment,
        $attachmentIds,
        $externalResources,
        $externalScripts,
        $embeds,
        $metrics['elements']
      );
      $embeds += preg_match_all('/\[(?:embed|video|audio)\b/i', $fragment);
    }

    $thumbnailId = function_exists('get_post_thumbnail_id')
      ? (int) get_post_thumbnail_id($postId)
      : 0;
    if ($thumbnailId > 0) {
      $attachmentIds[$thumbnailId] = $thumbnailId;
    }
    $this->collectAcfMedia($postId, $attachmentIds);

    foreach ($attachmentIds as $attachmentId) {
      $bytes = $this->getAttachmentBytes($attachmentId);
      if ($bytes <= 0) {
        continue;
      }
      $metrics['media_bytes'] += $bytes;
      if ($bytes >= self::LARGE_MEDIA_BYTES) {
        $metrics['large_media_count']++;
      }
    }

    $metrics['media_count'] = count($attachmentIds);
    $metrics['external_resources'] = count($externalResources);
    $metrics['external_scripts'] = count($externalScripts);
    $metrics['embeds'] = $embeds;
    $metrics['score'] = $this->calculateScore($metrics);
    $metrics['risk'] = $metrics['score'] >= 6
      ? 'high'
      : ($metrics['score'] >= 3 ? 'moderate' : 'low');

    return apply_filters('cife_performance_metrics', $metrics, $postId);
  }

  private function inspectHtml(
    $html,
    array &$attachmentIds,
    array &$externalResources,
    array &$externalScripts,
    &$embeds,
    &$elements
  ) {
    if (!class_exists('\DOMDocument')) {
      return;
    }

    $document = new \DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
      '<?xml encoding="utf-8" ?><body>' . $html . '</body>',
      LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
      return;
    }

    foreach ($document->getElementsByTagName('*') as $element) {
      $elements++;
      $tag = strtolower($element->tagName);
      if (in_array($tag, array('iframe', 'embed', 'object'), true)) {
        $embeds++;
      }

      $attributes = $this->resourceAttributes($tag, $element);
      foreach ($attributes as $attribute) {
        $url = trim($element->getAttribute($attribute));
        if ($url === '') {
          continue;
        }
        $this->collectAttachmentId($url, $attachmentIds);
        if ($this->isExternal($url)) {
          $externalResources[$url] = $url;
          if ($tag === 'script') {
            $externalScripts[$url] = $url;
          }
        }
      }
    }
  }

  private function resourceAttributes($tag, $element) {
    if ($tag === 'img' || $tag === 'script' || $tag === 'iframe' ||
      $tag === 'embed' || $tag === 'audio' || $tag === 'source') {
      return array('src');
    }
    if ($tag === 'video') {
      return array('src', 'poster');
    }
    if ($tag === 'object') {
      return array('data');
    }
    if ($tag === 'link' && stripos($element->getAttribute('rel'), 'stylesheet') !== false) {
      return array('href');
    }
    return array();
  }

  private function collectAttachmentId($url, array &$attachmentIds) {
    if (!function_exists('attachment_url_to_postid')) {
      return;
    }
    $attachmentId = (int) attachment_url_to_postid($url);
    if ($attachmentId > 0) {
      $attachmentIds[$attachmentId] = $attachmentId;
    }
  }

  private function collectAcfMedia($postId, array &$attachmentIds) {
    if (!function_exists('get_field_objects')) {
      return;
    }
    $fields = get_field_objects($postId, true, true);
    if (!is_array($fields)) {
      return;
    }
    foreach ($fields as $field) {
      $this->collectAcfFieldMedia($field, $attachmentIds);
    }
  }

  private function collectAcfFieldMedia($field, array &$attachmentIds) {
    if (!is_array($field) || empty($field['type'])) {
      return;
    }
    $value = isset($field['value']) ? $field['value'] : null;
    if ($field['type'] === 'image') {
      $this->addAttachmentValue($value, $attachmentIds);
      return;
    }
    if ($field['type'] === 'gallery' && is_array($value)) {
      foreach ($value as $image) {
        $this->addAttachmentValue($image, $attachmentIds);
      }
      return;
    }
    if (in_array($field['type'], array('group', 'clone'), true)) {
      $this->collectAcfMediaRow($field, $value, $attachmentIds);
      return;
    }
    if (in_array($field['type'], array('repeater', 'flexible_content'), true) &&
      is_array($value)) {
      foreach ($value as $row) {
        $this->collectAcfMediaRow($field, $row, $attachmentIds);
      }
    }
  }

  private function collectAcfMediaRow($field, $row, array &$attachmentIds) {
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
      $this->collectAcfFieldMedia($subField, $attachmentIds);
    }
  }

  private function addAttachmentValue($value, array &$attachmentIds) {
    $attachmentId = 0;
    if (is_numeric($value)) {
      $attachmentId = (int) $value;
    } elseif (is_array($value) && isset($value['ID'])) {
      $attachmentId = (int) $value['ID'];
    } elseif (is_array($value) && isset($value['id'])) {
      $attachmentId = (int) $value['id'];
    } elseif (is_array($value) && !empty($value['url'])) {
      $this->collectAttachmentId($value['url'], $attachmentIds);
      return;
    } elseif (is_string($value)) {
      $this->collectAttachmentId($value, $attachmentIds);
      return;
    }
    if ($attachmentId > 0) {
      $attachmentIds[$attachmentId] = $attachmentId;
    }
  }

  private function getAttachmentBytes($attachmentId) {
    $bytes = 0;
    if (function_exists('get_attached_file')) {
      $path = get_attached_file($attachmentId);
      if (is_string($path) && is_file($path)) {
        $bytes = (int) filesize($path);
      }
    }
    return max(0, (int) apply_filters(
      'cife_performance_attachment_bytes',
      $bytes,
      $attachmentId
    ));
  }

  private function isExternal($url) {
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) {
      return false;
    }
    $siteHost = wp_parse_url(home_url('/'), PHP_URL_HOST);
    return $siteHost && strtolower($host) !== strtolower($siteHost);
  }

  private function calculateScore($metrics) {
    $score = 0;
    $score += $metrics['content_bytes'] >= 262144
      ? 2
      : ($metrics['content_bytes'] >= 102400 ? 1 : 0);
    $score += $metrics['elements'] >= 1000
      ? 2
      : ($metrics['elements'] >= 500 ? 1 : 0);
    $score += $metrics['media_bytes'] >= 5242880
      ? 2
      : ($metrics['media_bytes'] >= 2097152 ? 1 : 0);
    $score += $metrics['large_media_count'] >= 3
      ? 2
      : ($metrics['large_media_count'] >= 1 ? 1 : 0);
    $score += $metrics['external_resources'] >= 4
      ? 2
      : ($metrics['external_resources'] >= 1 ? 1 : 0);
    $score += $metrics['embeds'] >= 3
      ? 2
      : ($metrics['embeds'] >= 1 ? 1 : 0);
    if ($metrics['external_scripts'] > 0) {
      $score++;
    }
    return $score;
  }

  private function getFingerprint($post) {
    $modified = is_object($post) && isset($post->post_modified_gmt)
      ? $post->post_modified_gmt
      : '';
    return hash('sha256', self::CACHE_VERSION . '|' . $modified);
  }
}
