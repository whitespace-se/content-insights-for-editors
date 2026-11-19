<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

/** Resolve same-site links using WordPress data only; never make HTTP requests. */
class LinkChecker {
  const REACHABLE = 'reachable';
  const BROKEN = 'broken';
  const INDETERMINATE = 'indeterminate';
  private $targetRepository;

  public function __construct(InternalTargetRepository $targetRepository = null) {
    $this->targetRepository = $targetRepository;
  }

  public function isInternal($url) {
    $urlHost = $this->normaliseHost($url);
    if (!$urlHost) {
      return false;
    }
    foreach (array(home_url('/'), site_url('/')) as $siteUrl) {
      if ($urlHost === $this->normaliseHost($siteUrl)) {
        return true;
      }
    }
    return false;
  }

  public function check($url) {
    if (!$this->isInternal($url)) {
      return self::INDETERMINATE;
    }
    $filtered = apply_filters('cife_broken_links_internal_url_status', null, $url);
    if (in_array($filtered, array(self::REACHABLE, self::BROKEN, self::INDETERMINATE), true)) {
      return $filtered;
    }
    if ($this->isSiteRoot($url)) {
      return self::REACHABLE;
    }
    $postId = (int) url_to_postid($url);
    if ($postId > 0) {
      $post = get_post($postId);
      if ($this->targetRepository) {
        $this->targetRepository->remember($url, $postId);
      }
      return $post && $post->post_status === 'publish' ? self::REACHABLE : self::BROKEN;
    }
    $uploadStatus = $this->checkUpload($url);
    if ($uploadStatus !== null) {
      return $uploadStatus;
    }
    return $this->targetRepository && $this->targetRepository->wasKnown($url)
      ? self::BROKEN
      : self::INDETERMINATE;
  }

  private function normaliseHost($url) {
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '') {
      return false;
    }
    $port = parse_url($url, PHP_URL_PORT);
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!$port || ($scheme === 'http' && (int) $port === 80) || ($scheme === 'https' && (int) $port === 443)) {
      $port = null;
    }
    return $host . ($port ? ':' . (int) $port : '');
  }

  private function isSiteRoot($url) {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $homePath = rtrim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    return rtrim($path, '/') === $homePath;
  }

  private function checkUpload($url) {
    if (!function_exists('wp_upload_dir')) {
      return null;
    }
    $uploads = wp_upload_dir(null, false);
    if (!empty($uploads['error']) || empty($uploads['baseurl']) || empty($uploads['basedir'])) {
      return null;
    }
    $urlPath = rawurldecode((string) parse_url($url, PHP_URL_PATH));
    $basePath = rtrim(rawurldecode((string) parse_url($uploads['baseurl'], PHP_URL_PATH)), '/');
    if ($basePath === '' || ($urlPath !== $basePath && strpos($urlPath, $basePath . '/') !== 0)) {
      return null;
    }
    $relative = ltrim(substr($urlPath, strlen($basePath)), '/');
    if ($relative === '' || strpos($relative, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $relative)) {
      return self::BROKEN;
    }
    return is_file(rtrim($uploads['basedir'], '/\\') . DIRECTORY_SEPARATOR . $relative)
      ? self::REACHABLE
      : self::BROKEN;
  }
}
