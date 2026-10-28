<?php

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {
  protected function tearDown(): void {
    $GLOBALS['cife_test_fields'] = array();
    $GLOBALS['cife_test_filters'] = array();
    $GLOBALS['cife_test_post_types'] = array('post', 'page', 'attachment', 'external_page', 'modal-content');
  }

  public function testRemovesHelperTypesFromSavedSelection() {
    $GLOBALS['cife_test_fields']['analysed_post_types'] = array(
      'page', 'attachment', 'external_page', 'modal-content', 'post',
    );

    $this->assertSame(array('page', 'post'), Settings::getSelectedPostTypes());
  }

  public function testRemovesHelperTypesFromDefaultPublicTypes() {
    $this->assertSame(array('post', 'page'), Settings::getAnalysedPostTypes());
  }

  public function testExcludedTypesCanBeExtended() {
    $GLOBALS['cife_test_filters']['cife_excluded_analysed_post_types'] = function ($types) {
      $types[] = 'post';
      return $types;
    };

    $this->assertSame(array('page'), Settings::getAnalysedPostTypes());
  }
}
