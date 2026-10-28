<?php

define('CONTENT_INSIGHTS_FOR_EDITORS_VERSION', '2.4.3-test');

$GLOBALS['cife_test_filters'] = array();
$GLOBALS['cife_test_site_url'] = 'https://example.test';
$GLOBALS['cife_test_internal_urls'] = array();
$GLOBALS['cife_test_posts'] = array();
$GLOBALS['cife_test_permalinks'] = array();
$GLOBALS['cife_test_fields'] = array();
$GLOBALS['cife_test_post_types'] = array('post', 'page', 'attachment', 'external_page', 'modal-content');

function apply_filters($hook, $value) {
  $args = func_get_args();
  if (isset($GLOBALS['cife_test_filters'][$hook])) {
    return call_user_func_array($GLOBALS['cife_test_filters'][$hook], array_slice($args, 1));
  }
  return $value;
}

function get_site_url() {
  return $GLOBALS['cife_test_site_url'];
}

function site_url($path = '') {
  return rtrim($GLOBALS['cife_test_site_url'], '/') . '/' . ltrim($path, '/');
}

function home_url($path = '') {
  return site_url($path);
}

function url_to_postid($url) {
  $id = array_search($url, $GLOBALS['cife_test_internal_urls'], true);
  return $id === false ? 0 : (is_int($id) ? $id : 1);
}

function get_post($postId) {
  return isset($GLOBALS['cife_test_posts'][$postId])
    ? $GLOBALS['cife_test_posts'][$postId]
    : null;
}

function get_permalink($postId) {
  return isset($GLOBALS['cife_test_permalinks'][$postId])
    ? $GLOBALS['cife_test_permalinks'][$postId]
    : false;
}

function get_field($name, $context = false) {
  return array_key_exists($name, $GLOBALS['cife_test_fields'])
    ? $GLOBALS['cife_test_fields'][$name]
    : false;
}

function sanitize_key($key) {
  return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
}

function get_post_types($args = array(), $output = 'names') {
  if ($output === 'objects') {
    $labels = array(
      'post' => 'Posts',
      'page' => 'Pages',
      'attachment' => 'Media',
      'external_page' => 'External pages',
      'modal-content' => 'Modal Content',
    );
    $postTypes = array();
    foreach ($GLOBALS['cife_test_post_types'] as $postType) {
      $postTypes[$postType] = (object) array(
        'name' => $postType,
        'label' => isset($labels[$postType]) ? $labels[$postType] : $postType,
      );
    }
    return $postTypes;
  }
  return $GLOBALS['cife_test_post_types'];
}

require_once dirname(__DIR__) . '/source/php/BrokenLinks/Repository.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/ExternalLinkRepository.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/InternalTargetRepository.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/ContentProvider.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/LinkExtractor.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/LinkChecker.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/Scanner.php';
require_once dirname(__DIR__) . '/source/php/Admin/Settings.php';
