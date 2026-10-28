<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class Scanner {
  const CACHE_PREFIX = 'cife_broken_link_';
  const CACHE_TTL = 21600;
  private $repository;
  private $contentProvider;
  private $extractor;
  private $checker;
  private $resultCache = array();

  public function __construct(
    Repository $repository = null,
    ContentProvider $contentProvider = null,
    LinkExtractor $extractor = null,
    LinkChecker $checker = null
  ) {
    $this->repository = $repository ?: new Repository();
    $this->contentProvider = $contentProvider ?: new ContentProvider();
    $this->extractor = $extractor ?: new LinkExtractor();
    $this->checker = $checker ?: new LinkChecker();
  }

  public function scanPost($postId) {
    $result = $this->scanPostBatch($postId, 0, array(), PHP_INT_MAX);
    return $result['saved'];
  }

  public function scanPostBatch(
    $postId,
    $offset = 0,
    array $brokenUrls = array(),
    $maxLinks = 20,
    $deadline = null
  ) {
    $post = get_post($postId);
    if (!$post) {
      $this->repository->deleteForPost($postId);
      return array(
        'complete' => true,
        'next_offset' => 0,
        'broken_urls' => array(),
        'saved' => true,
      );
    }

    $urls = $this->extractor->extract(
      $this->contentProvider->getFragments($postId),
      get_permalink($postId)
    );
    $previousBrokenUrls = array_flip($this->repository->getForPost($postId));
    $offset = max(0, (int) $offset);
    $end = min(count($urls), $offset + max(1, (int) $maxLinks));
    $index = $offset;
    for (; $index < $end; $index++) {
      if ($deadline !== null && microtime(true) >= $deadline && $index > $offset) {
        break;
      }
      $url = $urls[$index];
      $result = $this->getResult($url);
      if (
        $result === LinkChecker::BROKEN ||
        ($result === LinkChecker::INDETERMINATE && isset($previousBrokenUrls[$url]))
      ) {
        $brokenUrls[] = $url;
      }
    }

    $brokenUrls = array_values(array_unique($brokenUrls));
    $complete = $index >= count($urls);
    $saved = false;
    if ($complete) {
      $saved = $this->repository->replaceForPost($postId, $brokenUrls);
    }

    return array(
      'complete' => $complete,
      'next_offset' => $index,
      'broken_urls' => $brokenUrls,
      'saved' => $saved,
    );
  }

  private function getResult($url) {
    if (isset($this->resultCache[$url])) {
      return $this->resultCache[$url];
    }

    $cacheKey = self::CACHE_PREFIX . md5($url);
    $cached = function_exists('get_transient') ? get_transient($cacheKey) : false;
    if (in_array($cached, array(LinkChecker::REACHABLE, LinkChecker::BROKEN), true)) {
      $this->resultCache[$url] = $cached;
      return $cached;
    }

    $result = $this->checker->check($url);
    $this->resultCache[$url] = $result;
    if (
      function_exists('set_transient') &&
      in_array($result, array(LinkChecker::REACHABLE, LinkChecker::BROKEN), true)
    ) {
      $ttl = (int) apply_filters(
        'cife_broken_links_result_cache_ttl',
        self::CACHE_TTL,
        $url,
        $result
      );
      if ($ttl > 0) {
        set_transient($cacheKey, $result, $ttl);
      }
    }

    return $result;
  }
}
