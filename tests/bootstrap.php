<?php

define('CONTENT_INSIGHTS_FOR_EDITORS_VERSION', '2.3.0-test');

$GLOBALS['cife_test_filters'] = array();
$GLOBALS['cife_test_site_url'] = 'https://example.test';
$GLOBALS['cife_test_internal_urls'] = array();
$GLOBALS['cife_test_posts'] = array();
$GLOBALS['cife_test_permalinks'] = array();
$GLOBALS['cife_test_transients'] = array();

if (!class_exists('WP_Error')) {
  class WP_Error {}
}

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

function url_to_postid($url) {
  return in_array($url, $GLOBALS['cife_test_internal_urls'], true) ? 1 : 0;
}

function is_wp_error($value) {
  return $value instanceof WP_Error;
}

function wp_remote_retrieve_response_code($response) {
  return isset($response['response']['code'])
    ? $response['response']['code']
    : 0;
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

function get_transient($key) {
  return $GLOBALS['cife_test_transients'][$key] ?? false;
}

function set_transient($key, $value, $expiration) {
  $GLOBALS['cife_test_transients'][$key] = $value;
  return true;
}

require_once dirname(__DIR__) . '/source/php/BrokenLinks/Repository.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/ContentProvider.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/LinkExtractor.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/LinkChecker.php';
require_once dirname(__DIR__) . '/source/php/BrokenLinks/Scanner.php';
