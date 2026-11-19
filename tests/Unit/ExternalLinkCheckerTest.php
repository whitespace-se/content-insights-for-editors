<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkCheckConfig;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkChecker;
use PHPUnit\Framework\TestCase;

class ExternalLinkCheckerTest extends TestCase {
  private function config() {
    return new ExternalLinkCheckConfig(array(
      'hosts' => array('adda.se', 'www.adda.se'),
      'user_agent' => 'CIFE-LinkChecker/1.0 (+https://dela.skr.test; mailto:dela@skr.se)',
      'interval_days' => 30,
      'requests_per_minute' => 30,
    ));
  }

  private function response($code, $location = '') {
    return array(
      'response' => array('code' => $code),
      'headers' => $location ? array('location' => $location) : array(),
    );
  }

  public function testReportsSuccessfulHeadRequestAsOkay() {
    $calls = array();
    $checker = new ExternalLinkChecker(
      $this->config(),
      function ($method, $url, $args) use (&$calls) {
        $calls[] = array($method, $url, $args);
        return $this->response(200);
      }
    );

    $result = $checker->check('https://www.adda.se/resource');

    $this->assertSame(ExternalLinkChecker::OK, $result['status']);
    $this->assertSame(200, $result['http_code']);
    $this->assertCount(1, $calls);
    $this->assertSame('HEAD', $calls[0][0]);
    $this->assertSame($this->config()->getUserAgent(), $calls[0][2]['user-agent']);
    $this->assertSame(0, $calls[0][2]['redirection']);
  }

  public function testOnly404And410AreDefinitivelyBroken() {
    foreach (array(404, 410) as $status) {
      $checker = new ExternalLinkChecker(
        $this->config(),
        function () use ($status) { return $this->response($status); }
      );
      $this->assertSame(
        ExternalLinkChecker::BROKEN,
        $checker->check('https://adda.se/missing')['status']
      );
    }

    $checker = new ExternalLinkChecker(
      $this->config(),
      function () { return $this->response(403); }
    );
    $this->assertSame(
      ExternalLinkChecker::INCONCLUSIVE,
      $checker->check('https://adda.se/protected')['status']
    );
  }

  public function testFallsBackToLimitedGetWhenHeadIsUnsupported() {
    $methods = array();
    $checker = new ExternalLinkChecker(
      $this->config(),
      function ($method, $url, $args) use (&$methods) {
        $methods[] = array($method, $args);
        return $this->response($method === 'HEAD' ? 405 : 200);
      }
    );

    $result = $checker->check('https://adda.se/resource');

    $this->assertSame(ExternalLinkChecker::OK, $result['status']);
    $this->assertSame(array('HEAD', 'GET'), array_column($methods, 0));
    $this->assertSame(ExternalLinkChecker::MAX_RESPONSE_BYTES, $methods[1][1]['limit_response_size']);
  }

  public function testFollowsOnlyRedirectsWithinApprovedHosts() {
    $calls = array();
    $checker = new ExternalLinkChecker(
      $this->config(),
      function ($method, $url) use (&$calls) {
        $calls[] = $url;
        return count($calls) === 1
          ? $this->response(301, 'https://www.adda.se/new-location')
          : $this->response(200);
      }
    );

    $result = $checker->check('https://adda.se/old-location');
    $this->assertSame(ExternalLinkChecker::OK, $result['status']);
    $this->assertSame('https://www.adda.se/new-location', $result['final_url']);

    $calls = array();
    $checker = new ExternalLinkChecker(
      $this->config(),
      function ($method, $url) use (&$calls) {
        $calls[] = $url;
        return $this->response(302, 'https://outside.example/landing');
      }
    );
    $result = $checker->check('https://adda.se/leaving');
    $this->assertSame(ExternalLinkChecker::INCONCLUSIVE, $result['status']);
    $this->assertSame('redirect_not_approved', $result['message']);
    $this->assertCount(1, $calls);
  }

  public function testNeverRequestsAnUnapprovedUrl() {
    $called = false;
    $checker = new ExternalLinkChecker(
      $this->config(),
      function () use (&$called) { $called = true; return $this->response(200); }
    );

    $result = $checker->check('https://other.example/resource');

    $this->assertSame(ExternalLinkChecker::INCONCLUSIVE, $result['status']);
    $this->assertSame('not_approved', $result['message']);
    $this->assertFalse($called);
  }
}
