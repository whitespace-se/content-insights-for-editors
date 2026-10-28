<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class LinkChecker {
  const REACHABLE = 'reachable';
  const BROKEN = 'broken';
  const INDETERMINATE = 'indeterminate';

  private $requester;

  public function __construct($requester = null) {
    $this->requester = $requester;
  }

  public function check($url) {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
      return self::BROKEN;
    }

    $exceptedDomains = apply_filters(
      'cife_broken_links_excepted_domains',
      apply_filters('brokenLinks/External/ExceptedDomains', array())
    );

    if (in_array($host, (array) $exceptedDomains, true)) {
      return self::REACHABLE;
    }

    if ($this->isKnownInternalUrl($url)) {
      return self::REACHABLE;
    }

    $response = $this->request('HEAD', $url);
    if (is_wp_error($response)) {
      return self::INDETERMINATE;
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    if (in_array($status, array(405, 501), true)) {
      $response = $this->request('GET', $url);
      if (is_wp_error($response)) {
        return self::INDETERMINATE;
      }
      $status = (int) wp_remote_retrieve_response_code($response);
    }

    return $this->classifyStatus($status);
  }

  public function classifyStatus($status) {
    $reachableStatuses = apply_filters(
      'cife_broken_links_reachable_statuses',
      array(401, 403, 405, 406, 413)
    );

    if (($status >= 200 && $status < 400) || in_array($status, $reachableStatuses, true)) {
      return self::REACHABLE;
    }

    if (in_array($status, array(408, 425, 429), true) || $status >= 500 || $status === 0) {
      return self::INDETERMINATE;
    }

    return self::BROKEN;
  }

  private function request($method, $url) {
    $args = apply_filters(
      'cife_broken_links_http_args',
      array(
        'timeout' => 10,
        'redirection' => 5,
        'user-agent' => 'Content Insights for Editors/' . CONTENT_INSIGHTS_FOR_EDITORS_VERSION,
        'limit_response_size' => $method === 'GET' ? 16384 : null,
      ),
      $url,
      $method
    );

    if ($args['limit_response_size'] === null) {
      unset($args['limit_response_size']);
    }

    if (is_callable($this->requester)) {
      return call_user_func($this->requester, $method, $url, $args);
    }

    $siteHost = parse_url(get_site_url(), PHP_URL_HOST);
    $urlHost = parse_url($url, PHP_URL_HOST);
    $useSafeRequest = !$siteHost || !$urlHost || strcasecmp($siteHost, $urlHost) !== 0;
    $useSafeRequest = apply_filters(
      'cife_broken_links_use_safe_http',
      $useSafeRequest,
      $url
    );

    if ($method === 'GET') {
      return $useSafeRequest
        ? wp_safe_remote_get($url, $args)
        : wp_remote_get($url, $args);
    }

    return $useSafeRequest
      ? wp_safe_remote_head($url, $args)
      : wp_remote_head($url, $args);
  }

  private function isKnownInternalUrl($url) {
    $siteHost = parse_url(get_site_url(), PHP_URL_HOST);
    $urlHost = parse_url($url, PHP_URL_HOST);

    if (!$siteHost || !$urlHost || strcasecmp($siteHost, $urlHost) !== 0) {
      return false;
    }

    return url_to_postid($url) > 0;
  }
}
