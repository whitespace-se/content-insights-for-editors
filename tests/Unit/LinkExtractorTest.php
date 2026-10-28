<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\LinkExtractor;
use PHPUnit\Framework\TestCase;

class LinkExtractorTest extends TestCase {
  public function testExtractsNormalizesAndDeduplicatesWebLinks() {
    $extractor = new LinkExtractor();
    $links = $extractor->extract(
      array(
        '<a href="https://external.test/a path#part">External</a>',
        '<a href="/about">About</a><a href="../contact">Contact</a>',
        '<a href="mailto:test@example.test">Mail</a>',
        '<a href="https://external.test/a path#other">Duplicate</a>',
      ),
      'https://example.test/news/article'
    );

    $this->assertSame(
      array(
        'https://external.test/a%20path',
        'https://example.test/about',
        'https://example.test/contact',
      ),
      $links
    );
  }

  public function testSupportsProtocolRelativeAndInternationalHosts() {
    $extractor = new LinkExtractor();
    $links = $extractor->extract(
      array('<a href="//räksmörgås.test/sida?a=1&amp;b=2">IDN</a>'),
      'https://example.test/page'
    );

    $this->assertSame(
      array('https://räksmörgås.test/sida?a=1&b=2'),
      $links
    );
  }
}
