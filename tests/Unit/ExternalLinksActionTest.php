<?php

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\ExternalLinks;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkCheckConfig;
use PHPUnit\Framework\TestCase;

class ExternalLinksActionTest extends TestCase {
  private function view() {
    return new class extends ExternalLinks {
      public function __construct() {}

      public function showsManualReviewAction($item, ExternalLinkCheckConfig $config) {
        return $this->shouldShowManualReviewAction($item, $config);
      }
    };
  }

  private function config($hosts = array('adda.se', 'www.adda.se')) {
    return new ExternalLinkCheckConfig(array(
      'hosts' => $hosts,
      'user_agent' => 'CIFE-LinkChecker/1.0',
    ));
  }

  private function link($status = null, $automaticCheckedAt = null, $checkedAt = null) {
    return (object) array(
      'url' => 'https://www.adda.se/resource',
      'automatic_status' => $status,
      'automatic_checked_at' => $automaticCheckedAt,
      'checked_at' => $checkedAt,
    );
  }

  public function testManualReviewOnlyLinkAlwaysShowsAction() {
    $link = $this->link('ok', '2026-11-19 12:00:00', null);

    $this->assertTrue(
      $this->view()->showsManualReviewAction($link, $this->config(array()))
    );
  }

  public function testApprovedLinkAwaitingAutomaticCheckShowsAction() {
    $this->assertTrue(
      $this->view()->showsManualReviewAction($this->link(), $this->config())
    );
  }

  public function testBrokenAndInconclusiveAutomaticResultsShowAction() {
    foreach (array('broken', 'inconclusive') as $status) {
      $this->assertTrue(
        $this->view()->showsManualReviewAction(
          $this->link($status, '2026-11-19 12:00:00'),
          $this->config()
        )
      );
    }
  }

  public function testLaterProblematicAutomaticResultRestoresActionAfterManualReview() {
    $this->assertTrue(
      $this->view()->showsManualReviewAction(
        $this->link(
          'inconclusive',
          '2026-11-20 12:00:00',
          '2026-11-19 12:00:00'
        ),
        $this->config()
      )
    );
  }

  public function testLatestSuccessfulAutomaticResultHidesAction() {
    $this->assertFalse(
      $this->view()->showsManualReviewAction(
        $this->link('ok', '2026-11-19 12:00:00', '2026-11-18 12:00:00'),
        $this->config()
      )
    );
    $this->assertFalse(
      $this->view()->showsManualReviewAction(
        $this->link('ok', '2026-11-19 12:00:00', null),
        $this->config()
      )
    );
  }

  public function testLaterOrEqualManualReviewRestoresAction() {
    foreach (array('2026-11-19 12:00:00', '2026-11-20 12:00:00') as $checkedAt) {
      $this->assertTrue(
        $this->view()->showsManualReviewAction(
          $this->link('ok', '2026-11-19 12:00:00', $checkedAt),
          $this->config()
        )
      );
    }
  }
}
