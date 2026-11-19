<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class ExternalLinkChecker {
  const OK = 'ok';
  const BROKEN = 'broken';
  const INCONCLUSIVE = 'inconclusive';
  const MAX_REDIRECTS = 3;
  const MAX_RESPONSE_BYTES = 65536;

  private $config;
  private $requester;
  private $lastRequestStartedAt;

  public function __construct(
    ExternalLinkCheckConfig $config = null,
    callable $requester = null
  ) {
    $this->config = $config ?: new ExternalLinkCheckConfig();
    $this->requester = $requester;
  }

  public function check($url) {
    if (!$this->config->isApprovedUrl($url)) {
      return $this->result(
        self::INCONCLUSIVE,
        null,
        $url,
        'not_approved'
      );
    }

    $currentUrl = $url;
    for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
      $response = $this->request('HEAD', $currentUrl);
      if (is_wp_error($response)) {
        return $this->result(
          self::INCONCLUSIVE,
          null,
          $currentUrl,
          $response->get_error_message()
        );
      }

      $status = (int) wp_remote_retrieve_response_code($response);
      if (in_array($status, array(405, 501), true)) {
        $response = $this->request('GET', $currentUrl);
        if (is_wp_error($response)) {
          return $this->result(
            self::INCONCLUSIVE,
            null,
            $currentUrl,
            $response->get_error_message()
          );
        }
        $status = (int) wp_remote_retrieve_response_code($response);
      }

      if ($this->isRedirect($status)) {
        $location = wp_remote_retrieve_header($response, 'location');
        $nextUrl = $this->absoluteRedirectUrl($location, $currentUrl);
        if (!$nextUrl) {
          return $this->result(
            self::INCONCLUSIVE,
            $status,
            $currentUrl,
            'invalid_redirect'
          );
        }
        if (!$this->config->isApprovedUrl($nextUrl)) {
          return $this->result(
            self::INCONCLUSIVE,
            $status,
            $nextUrl,
            'redirect_not_approved'
          );
        }
        if ($redirects === self::MAX_REDIRECTS) {
          return $this->result(
            self::INCONCLUSIVE,
            $status,
            $nextUrl,
            'too_many_redirects'
          );
        }
        $currentUrl = $nextUrl;
        continue;
      }

      if ($status >= 200 && $status < 300) {
        return $this->result(self::OK, $status, $currentUrl, 'reachable');
      }
      if (in_array($status, array(404, 410), true)) {
        return $this->result(self::BROKEN, $status, $currentUrl, 'not_found');
      }
      return $this->result(
        self::INCONCLUSIVE,
        $status ?: null,
        $currentUrl,
        'http_error'
      );
    }

    return $this->result(
      self::INCONCLUSIVE,
      null,
      $currentUrl,
      'too_many_redirects'
    );
  }

  private function request($method, $url) {
    $args = array(
      'timeout' => 10,
      'redirection' => 0,
      'reject_unsafe_urls' => true,
      'user-agent' => $this->config->getUserAgent(),
    );
    if ($method === 'GET') {
      $args['headers'] = array('Range' => 'bytes=0-' . (self::MAX_RESPONSE_BYTES - 1));
      $args['limit_response_size'] = self::MAX_RESPONSE_BYTES;
    }
    $args = apply_filters(
      'cife_external_link_check_http_args',
      $args,
      $method,
      $url
    );

    if ($this->requester) {
      return call_user_func($this->requester, $method, $url, $args);
    }
    $this->waitForRequestSlot();
    $this->lastRequestStartedAt = microtime(true);
    return $method === 'GET'
      ? wp_safe_remote_get($url, $args)
      : wp_safe_remote_head($url, $args);
  }

  private function waitForRequestSlot() {
    if ($this->lastRequestStartedAt === null) {
      return;
    }
    $remaining = $this->config->getRequestDelay() -
      (microtime(true) - $this->lastRequestStartedAt);
    if ($remaining > 0) {
      usleep((int) ceil($remaining * 1000000));
    }
  }

  private function isRedirect($status) {
    return in_array($status, array(301, 302, 303, 307, 308), true);
  }

  private function absoluteRedirectUrl($location, $baseUrl) {
    if (!is_string($location) || trim($location) === '') {
      return false;
    }
    return \WP_Http::make_absolute_url(trim($location), $baseUrl);
  }

  private function result($status, $httpCode, $finalUrl, $message) {
    return array(
      'status' => $status,
      'http_code' => $httpCode === null ? null : (int) $httpCode,
      'final_url' => (string) $finalUrl,
      'message' => sanitize_text_field((string) $message),
    );
  }
}
