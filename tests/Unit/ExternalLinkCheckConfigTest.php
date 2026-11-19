<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkCheckConfig;
use PHPUnit\Framework\TestCase;

class ExternalLinkCheckConfigTest extends TestCase {
  protected function tearDown(): void {
    $GLOBALS['cife_test_filters'] = array();
  }

  public function testAutomaticChecksAreDisabledByDefault() {
    $config = new ExternalLinkCheckConfig();

    $this->assertFalse($config->isEnabled());
    $this->assertSame(array(), $config->getHosts());
  }

  public function testRequiresBothApprovedHostsAndUserAgent() {
    $withoutAgent = new ExternalLinkCheckConfig(array(
      'hosts' => array('www.adda.se'),
    ));
    $withoutHosts = new ExternalLinkCheckConfig(array(
      'user_agent' => 'CIFE-LinkChecker/1.0',
    ));

    $this->assertFalse($withoutAgent->isEnabled());
    $this->assertFalse($withoutHosts->isEnabled());
  }

  public function testApprovesOnlyExactHttpsHostsOnStandardPort() {
    $config = new ExternalLinkCheckConfig(array(
      'hosts' => array('ADDA.SE.', 'www.adda.se', '*.skr.se', 'https://invalid.test'),
      'user_agent' => "CIFE-LinkChecker/1.0\r\nInjected",
      'interval_days' => 30,
      'requests_per_minute' => 30,
    ));

    $this->assertTrue($config->isEnabled());
    $this->assertSame(array('adda.se', 'www.adda.se'), $config->getHosts());
    $this->assertSame('CIFE-LinkChecker/1.0 Injected', $config->getUserAgent());
    $this->assertTrue($config->isApprovedUrl('https://adda.se/resource'));
    $this->assertTrue($config->isApprovedUrl('https://www.adda.se:443/resource'));
    $this->assertFalse($config->isApprovedUrl('http://adda.se/resource'));
    $this->assertFalse($config->isApprovedUrl('https://jobs.adda.se/resource'));
    $this->assertFalse($config->isApprovedUrl('https://adda.se:8443/resource'));
    $this->assertFalse($config->isApprovedUrl('https://user:pass@adda.se/resource'));
    $this->assertSame(2, $config->getRequestDelay());
  }
}
