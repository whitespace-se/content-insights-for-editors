<?php

use CONTENT_INSIGHTS_FOR_EDITORS\Performance\Analyzer;
use PHPUnit\Framework\TestCase;

class PerformanceAnalyzerTest extends TestCase {
  protected function tearDown(): void {
    $GLOBALS['cife_test_posts'] = array();
    $GLOBALS['cife_test_post_meta'] = array();
    $GLOBALS['cife_test_field_objects'] = array();
    $GLOBALS['cife_test_attachment_ids'] = array();
    $GLOBALS['cife_test_post_thumbnails'] = array();
    $GLOBALS['cife_test_filters'] = array();
  }

  public function testCalculatesExplainableContentRiskMetrics() {
    $postId = 701;
    $GLOBALS['cife_test_posts'][$postId] = (object) array(
      'ID' => $postId,
      'post_content' => implode('', array(
        '<img src="https://example.test/app/uploads/local.jpg">',
        '<img src="https://cdn.test/remote.jpg">',
        '<script src="https://scripts.test/widget.js"></script>',
        '<iframe src="https://video.test/embed/1"></iframe>',
        '<a href="https://ordinary-link.test/page">External link</a>',
      )),
    );
    $GLOBALS['cife_test_attachment_ids'][
      'https://example.test/app/uploads/local.jpg'
    ] = 90;
    $GLOBALS['cife_test_post_thumbnails'][$postId] = 92;
    $GLOBALS['cife_test_field_objects'][$postId] = array(
      'editorial_image' => array(
        'name' => 'editorial_image',
        'type' => 'image',
        'value' => array('ID' => 91),
      ),
    );
    $GLOBALS['cife_test_filters']['cife_performance_attachment_bytes'] = function (
      $bytes,
      $attachmentId
    ) {
      $sizes = array(90 => 700000, 91 => 800000, 92 => 100000);
      return isset($sizes[$attachmentId]) ? $sizes[$attachmentId] : $bytes;
    };

    $metrics = (new Analyzer())->analyze($postId);

    $this->assertSame(3, $metrics['media_count']);
    $this->assertSame(1600000, $metrics['media_bytes']);
    $this->assertSame(2, $metrics['large_media_count']);
    $this->assertSame(3, $metrics['external_resources']);
    $this->assertSame(1, $metrics['external_scripts']);
    $this->assertSame(1, $metrics['embeds']);
    $this->assertSame(4, $metrics['score']);
    $this->assertSame('moderate', $metrics['risk']);
  }

  public function testStoresAndReusesMetricsForUnchangedPost() {
    $postId = 702;
    $GLOBALS['cife_test_posts'][$postId] = (object) array(
      'ID' => $postId,
      'post_modified_gmt' => '2026-10-30 09:00:00',
      'post_content' => '<p>Compact page</p>',
    );
    $analyzer = new Analyzer();
    $first = $analyzer->getMetrics($postId);

    $this->assertSame('low', $first['risk']);
    $this->assertSame(
      'low',
      $GLOBALS['cife_test_post_meta'][$postId][Analyzer::RISK_META_KEY]
    );

    $GLOBALS['cife_test_posts'][$postId]->post_content = str_repeat('<div>Large</div>', 40000);
    $cached = $analyzer->getMetrics($postId);
    $this->assertSame($first, $cached);

    $GLOBALS['cife_test_posts'][$postId]->post_modified_gmt = '2026-10-30 09:01:00';
    $refreshed = $analyzer->getMetrics($postId);
    $this->assertNotSame($first['content_bytes'], $refreshed['content_bytes']);
  }
}
