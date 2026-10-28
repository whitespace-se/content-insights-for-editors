<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class Scanner {
  private $repository;
  private $externalRepository;
  private $contentProvider;
  private $extractor;
  private $checker;

  public function __construct(
    Repository $repository = null,
    ContentProvider $contentProvider = null,
    LinkExtractor $extractor = null,
    LinkChecker $checker = null,
    ExternalLinkRepository $externalRepository = null
  ) {
    $this->repository = $repository ?: new Repository();
    $this->contentProvider = $contentProvider ?: new ContentProvider();
    $this->extractor = $extractor ?: new LinkExtractor();
    $this->checker = $checker ?: new LinkChecker(new InternalTargetRepository());
    $this->externalRepository = $externalRepository ?: new ExternalLinkRepository();
  }

  public function scanPost($postId) {
    $result = $this->scanPostBatch($postId, 0, array(), PHP_INT_MAX, null, array());
    return $result['saved'];
  }

  public function scanPostBatch(
    $postId,
    $offset = 0,
    array $brokenUrls = array(),
    $maxLinks = 20,
    $deadline = null,
    array $externalUrls = array()
  ) {
    $post = get_post($postId);
    if (!$post) {
      $this->repository->deleteForPost($postId);
      $this->externalRepository->deleteForPost($postId);
      return array(
        'complete' => true,
        'next_offset' => 0,
        'broken_urls' => array(),
        'external_urls' => array(),
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
      if (!$this->checker->isInternal($url)) {
        $externalUrls[] = $url;
        continue;
      }
      $result = $this->checker->check($url);
      if ($result === LinkChecker::BROKEN ||
        ($result === LinkChecker::INDETERMINATE && isset($previousBrokenUrls[$url]))) {
          $brokenUrls[] = $url;
      }
    }

    $brokenUrls = array_values(array_unique($brokenUrls));
    $externalUrls = array_values(array_unique($externalUrls));
    $complete = $index >= count($urls);
    $saved = false;
    if ($complete) {
      $saved = $this->repository->replaceForPost($postId, $brokenUrls) &&
        $this->externalRepository->replaceForPost($postId, $externalUrls);
    }

    return array(
      'complete' => $complete,
      'next_offset' => $index,
      'broken_urls' => $brokenUrls,
      'external_urls' => $externalUrls,
      'saved' => $saved,
    );
  }
}
