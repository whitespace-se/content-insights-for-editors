<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\LinkChecker;
use PHPUnit\Framework\TestCase;

class LinkCheckerTest extends TestCase {
  protected function tearDown(): void {
    $GLOBALS['cife_test_filters'] = array();
    $GLOBALS['cife_test_internal_urls'] = array();
  }

  public function testClassifiesHttpStatuses() {
    $checker = new LinkChecker();

    $this->assertSame(LinkChecker::REACHABLE, $checker->classifyStatus(204));
    $this->assertSame(LinkChecker::REACHABLE, $checker->classifyStatus(301));
    $this->assertSame(LinkChecker::REACHABLE, $checker->classifyStatus(401));
    $this->assertSame(LinkChecker::BROKEN, $checker->classifyStatus(404));
    $this->assertSame(LinkChecker::BROKEN, $checker->classifyStatus(410));
    $this->assertSame(LinkChecker::INDETERMINATE, $checker->classifyStatus(429));
    $this->assertSame(LinkChecker::INDETERMINATE, $checker->classifyStatus(503));
  }

  public function testFallsBackToGetWhenHeadIsUnsupported() {
    $methods = array();
    $checker = new LinkChecker(function ($method) use (&$methods) {
      $methods[] = $method;
      return array(
        'response' => array('code' => $method === 'HEAD' ? 405 : 200),
      );
    });

    $this->assertSame(
      LinkChecker::REACHABLE,
      $checker->check('https://external.test/resource')
    );
    $this->assertSame(array('HEAD', 'GET'), $methods);
  }

  public function testTreatsTransportErrorsAsIndeterminate() {
    $checker = new LinkChecker(function () {
      return new WP_Error();
    });

    $this->assertSame(
      LinkChecker::INDETERMINATE,
      $checker->check('https://external.test/resource')
    );
  }

  public function testSupportsLegacyExceptionDomainFilter() {
    $GLOBALS['cife_test_filters']['brokenLinks/External/ExceptedDomains'] = function () {
      return array('intranet.test');
    };
    $checker = new LinkChecker(function () {
      $this->fail('Excepted domains must not be requested.');
    });

    $this->assertSame(
      LinkChecker::REACHABLE,
      $checker->check('https://intranet.test/page')
    );
  }

  public function testKnownInternalPostsAreReachableWithoutARequest() {
    $url = 'https://example.test/known-page';
    $GLOBALS['cife_test_internal_urls'][] = $url;
    $checker = new LinkChecker(function () {
      $this->fail('Known internal posts must not be requested.');
    });

    $this->assertSame(LinkChecker::REACHABLE, $checker->check($url));
  }
}
