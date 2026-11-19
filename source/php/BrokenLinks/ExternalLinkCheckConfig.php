<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class ExternalLinkCheckConfig {
  const DEFAULT_INTERVAL_DAYS = 30;
  const DEFAULT_REQUESTS_PER_MINUTE = 30;

  private $hosts = array();
  private $userAgent = '';
  private $intervalDays = self::DEFAULT_INTERVAL_DAYS;
  private $requestsPerMinute = self::DEFAULT_REQUESTS_PER_MINUTE;

  public function __construct($values = null) {
    if ($values === null) {
      $values = apply_filters(
        'cife_external_link_check_config',
        array(
          'hosts' => array(),
          'user_agent' => '',
          'interval_days' => self::DEFAULT_INTERVAL_DAYS,
          'requests_per_minute' => self::DEFAULT_REQUESTS_PER_MINUTE,
        )
      );
    }
    $values = is_array($values) ? $values : array();
    $this->hosts = $this->sanitizeHosts(
      isset($values['hosts']) ? $values['hosts'] : array()
    );
    $this->userAgent = $this->sanitizeUserAgent(
      isset($values['user_agent']) ? $values['user_agent'] : ''
    );
    $this->intervalDays = $this->boundedInteger(
      isset($values['interval_days']) ? $values['interval_days'] : self::DEFAULT_INTERVAL_DAYS,
      1,
      365,
      self::DEFAULT_INTERVAL_DAYS
    );
    $this->requestsPerMinute = $this->boundedInteger(
      isset($values['requests_per_minute'])
        ? $values['requests_per_minute']
        : self::DEFAULT_REQUESTS_PER_MINUTE,
      1,
      60,
      self::DEFAULT_REQUESTS_PER_MINUTE
    );
  }

  public function isEnabled() {
    return !empty($this->hosts) && $this->userAgent !== '';
  }

  public function getHosts() {
    return $this->hosts;
  }

  public function getUserAgent() {
    return $this->userAgent;
  }

  public function getIntervalDays() {
    return $this->intervalDays;
  }

  public function getRequestsPerMinute() {
    return $this->requestsPerMinute;
  }

  public function getRequestDelay() {
    return (int) ceil(MINUTE_IN_SECONDS / $this->requestsPerMinute);
  }

  public function isApprovedUrl($url) {
    if (!is_string($url) || $url === '') {
      return false;
    }
    $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
    $host = strtolower(rtrim((string) wp_parse_url($url, PHP_URL_HOST), '.'));
    $port = wp_parse_url($url, PHP_URL_PORT);
    $user = wp_parse_url($url, PHP_URL_USER);
    $password = wp_parse_url($url, PHP_URL_PASS);

    return $scheme === 'https' &&
      $host !== '' &&
      in_array($host, $this->hosts, true) &&
      ($port === null || (int) $port === 443) &&
      $user === null &&
      $password === null;
  }

  private function sanitizeHosts($hosts) {
    $valid = array();
    foreach ((array) $hosts as $host) {
      $host = strtolower(rtrim(trim((string) $host), '.'));
      if (
        $host === '' ||
        strpos($host, '*') !== false ||
        !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)
      ) {
        continue;
      }
      $valid[$host] = $host;
    }
    return array_values($valid);
  }

  private function sanitizeUserAgent($userAgent) {
    $userAgent = preg_replace('/[\r\n]+/', ' ', trim((string) $userAgent));
    return substr($userAgent, 0, 255);
  }

  private function boundedInteger($value, $minimum, $maximum, $fallback) {
    if (!is_numeric($value)) {
      return $fallback;
    }
    return max($minimum, min($maximum, (int) $value));
  }
}
