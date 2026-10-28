<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\LinkChecker;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\InternalTargetRepository;
use PHPUnit\Framework\TestCase;

class LinkCheckerTest extends TestCase {
  protected function tearDown(): void {
    $GLOBALS['cife_test_filters'] = array();
    $GLOBALS['cife_test_internal_urls'] = array();
    $GLOBALS['cife_test_posts'] = array();
  }

  public function testSeparatesInternalAndExternalHosts() {
    $checker = new LinkChecker();
    $this->assertTrue($checker->isInternal('https://example.test/a'));
    $this->assertTrue($checker->isInternal('https://EXAMPLE.test/a'));
    $this->assertFalse($checker->isInternal('https://external.test/a'));
  }

  public function testExternalUrlsAreAlwaysIndeterminateWithoutRequests() {
    $checker = new LinkChecker();
    $this->assertSame(LinkChecker::INDETERMINATE, $checker->check('https://external.test/a'));
  }

  public function testPublishedInternalPostsAreReachableFromWordPressData() {
    $url = 'https://example.test/known-page';
    $GLOBALS['cife_test_internal_urls'][12] = $url;
    $GLOBALS['cife_test_posts'][12] = (object) array('ID' => 12, 'post_status' => 'publish');
    $this->assertSame(LinkChecker::REACHABLE, (new LinkChecker())->check($url));
  }

  public function testNonPublishedInternalPostsAreBroken() {
    $url = 'https://example.test/draft-page';
    $GLOBALS['cife_test_internal_urls'][13] = $url;
    $GLOBALS['cife_test_posts'][13] = (object) array('ID' => 13, 'post_status' => 'draft');
    $this->assertSame(LinkChecker::BROKEN, (new LinkChecker())->check($url));
  }

  public function testUnknownInternalRoutesAreIndeterminate() {
    $this->assertSame(
      LinkChecker::INDETERMINATE,
      (new LinkChecker())->check('https://example.test/custom-route')
    );
  }

  public function testPreviouslyKnownInternalTargetBecomesBrokenWhenItDisappears() {
    $history = new InMemoryInternalTargetRepository();
    $url = 'https://example.test/deleted-page';
    $history->remember($url, 99);
    $this->assertSame(LinkChecker::BROKEN, (new LinkChecker($history))->check($url));
  }

  public function testInternalResolverCanBeExtendedByFilter() {
    $GLOBALS['cife_test_filters']['cife_broken_links_internal_url_status'] = function () {
      return LinkChecker::BROKEN;
    };
    $this->assertSame(
      LinkChecker::BROKEN,
      (new LinkChecker())->check('https://example.test/custom-route')
    );
  }
}

class InMemoryInternalTargetRepository extends InternalTargetRepository {
  private $urls = array();
  public function remember($url, $postId) { $this->urls[$url] = $postId; return true; }
  public function wasKnown($url) { return isset($this->urls[$url]); }
}
