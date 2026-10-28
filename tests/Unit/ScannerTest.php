<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ContentProvider;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\LinkChecker;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\LinkExtractor;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Repository;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Scanner;
use PHPUnit\Framework\TestCase;

class ScannerTest extends TestCase {
  protected function setUp(): void {
    $GLOBALS['cife_test_transients'] = array();
  }

  public function testPreservesPreviousBrokenResultWhenRecheckIsIndeterminate() {
    $postId = 42;
    $GLOBALS['cife_test_posts'][$postId] = (object) array('ID' => $postId);
    $GLOBALS['cife_test_permalinks'][$postId] = 'https://example.test/page';

    $repository = new InMemoryBrokenLinkRepository(
      array('https://timeout.test/link', 'https://fixed.test/link')
    );
    $scanner = new Scanner(
      $repository,
      new FixedContentProvider(),
      new FixedLinkExtractor(),
      new FixedLinkChecker()
    );

    $this->assertTrue($scanner->scanPost($postId));
    $this->assertSame(
      array('https://broken.test/link', 'https://timeout.test/link'),
      $repository->saved
    );
  }

  public function testResumesAUrlBatchWithoutReplacingPartialResults() {
    $postId = 43;
    $GLOBALS['cife_test_posts'][$postId] = (object) array('ID' => $postId);
    $GLOBALS['cife_test_permalinks'][$postId] = 'https://example.test/batched-page';
    $repository = new InMemoryBrokenLinkRepository(array());
    $scanner = new Scanner(
      $repository,
      new FixedContentProvider(),
      new FixedLinkExtractor(),
      new FixedLinkChecker()
    );

    $first = $scanner->scanPostBatch($postId, 0, array(), 2);
    $this->assertFalse($first['complete']);
    $this->assertSame(2, $first['next_offset']);
    $this->assertSame(array(), $repository->saved);

    $second = $scanner->scanPostBatch(
      $postId,
      $first['next_offset'],
      $first['broken_urls'],
      2
    );
    $this->assertTrue($second['complete']);
    $this->assertTrue($second['saved']);
    $this->assertSame(array('https://broken.test/link'), $repository->saved);
  }

  public function testCachesDefinitiveResultsAcrossScannerInstances() {
    $postId = 44;
    $GLOBALS['cife_test_posts'][$postId] = (object) array('ID' => $postId);
    $GLOBALS['cife_test_permalinks'][$postId] = 'https://example.test/cached-page';
    $checker = new CountingLinkChecker();

    (new Scanner(
      new InMemoryBrokenLinkRepository(array()),
      new FixedContentProvider(),
      new FixedLinkExtractor(),
      $checker
    ))->scanPost($postId);
    (new Scanner(
      new InMemoryBrokenLinkRepository(array()),
      new FixedContentProvider(),
      new FixedLinkExtractor(),
      $checker
    ))->scanPost($postId);

    $this->assertSame(4, $checker->calls);
  }
}

class InMemoryBrokenLinkRepository extends Repository {
  private $previous;
  public $saved = array();

  public function __construct(array $previous) {
    $this->previous = $previous;
  }

  public function getForPost($postId) {
    return $this->previous;
  }

  public function replaceForPost($postId, array $urls) {
    $this->saved = $urls;
    return true;
  }
}

class FixedContentProvider extends ContentProvider {
  public function getFragments($postId) {
    return array('unused');
  }
}

class FixedLinkExtractor extends LinkExtractor {
  public function extract(array $fragments, $baseUrl) {
    return array(
      'https://broken.test/link',
      'https://timeout.test/link',
      'https://fixed.test/link',
    );
  }
}

class FixedLinkChecker extends LinkChecker {
  public function check($url) {
    if (strpos($url, 'broken.test') !== false) {
      return self::BROKEN;
    }
    if (strpos($url, 'timeout.test') !== false) {
      return self::INDETERMINATE;
    }
    return self::REACHABLE;
  }
}

class CountingLinkChecker extends FixedLinkChecker {
  public $calls = 0;

  public function check($url) {
    $this->calls++;
    return parent::check($url);
  }
}
