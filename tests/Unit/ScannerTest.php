<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ContentProvider;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkRepository;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\LinkChecker;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\LinkExtractor;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Repository;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Scanner;
use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase {
  public function testChecksOnlyInternalLinksAndInventoriesExternalLinks() {
    $postId = 42;
    $GLOBALS['cife_test_posts'][$postId] = (object) array(
      'ID' => $postId,
      'post_content' => '',
    );
    $GLOBALS['cife_test_permalinks'][$postId] = 'https://example.test/page';
    $broken = new InMemoryBrokenLinkRepository(array('https://example.test/timeout'));
    $external = new InMemoryExternalLinkRepository();
    $checker = new FixedInternalLinkChecker();
    $scanner = new Scanner($broken, new FixedContentProvider(), new FixedLinkExtractor(), $checker, $external);

    $this->assertTrue($scanner->scanPost($postId));
    $this->assertSame(array('https://example.test/broken', 'https://example.test/timeout'), $broken->saved);
    $this->assertSame(array('https://external.test/link'), $external->saved);
    $this->assertSame(3, $checker->calls);
  }

  public function testResumesBothResultSetsWithoutSavingPartialData() {
    $postId = 43;
    $GLOBALS['cife_test_posts'][$postId] = (object) array(
      'ID' => $postId,
      'post_content' => '',
    );
    $GLOBALS['cife_test_permalinks'][$postId] = 'https://example.test/page';
    $broken = new InMemoryBrokenLinkRepository(array());
    $external = new InMemoryExternalLinkRepository();
    $scanner = new Scanner($broken, new FixedContentProvider(), new FixedLinkExtractor(), new FixedInternalLinkChecker(), $external);

    $first = $scanner->scanPostBatch($postId, 0, array(), 2, null, array());
    $this->assertFalse($first['complete']);
    $this->assertSame(array('https://external.test/link'), $first['external_urls']);
    $this->assertSame(array(), $broken->saved);

    $second = $scanner->scanPostBatch(
      $postId, $first['next_offset'], $first['broken_urls'], 10, null, $first['external_urls']
    );
    $this->assertTrue($second['complete']);
    $this->assertSame(array('https://example.test/broken'), $broken->saved);
    $this->assertSame(array('https://external.test/link'), $external->saved);
  }
}

class InMemoryBrokenLinkRepository extends Repository {
  private $previous;
  public $saved = array();
  public function __construct(array $previous) { $this->previous = $previous; }
  public function getForPost($postId) { return $this->previous; }
  public function replaceForPost($postId, array $urls) { $this->saved = $urls; return true; }
  public function deleteForPost($postId) { return true; }
}

class InMemoryExternalLinkRepository extends ExternalLinkRepository {
  public $saved = array();
  public function replaceForPost($postId, array $urls) { $this->saved = $urls; return true; }
  public function deleteForPost($postId) { return true; }
}

class FixedContentProvider extends ContentProvider {
  public function getFragments($postId) { return array('unused'); }
}

class FixedLinkExtractor extends LinkExtractor {
  public function extract(array $fragments, $baseUrl) {
    return array(
      'https://external.test/link',
      'https://example.test/reachable',
      'https://example.test/broken',
      'https://example.test/timeout',
    );
  }
}

class FixedInternalLinkChecker extends LinkChecker {
  public $calls = 0;
  public function check($url) {
    $this->calls++;
    if (strpos($url, '/broken') !== false) { return self::BROKEN; }
    if (strpos($url, '/timeout') !== false) { return self::INDETERMINATE; }
    return self::REACHABLE;
  }
}
