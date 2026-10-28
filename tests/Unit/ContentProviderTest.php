<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ContentProvider;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\LinkExtractor;
use PHPUnit\Framework\TestCase;

class ContentProviderTest extends TestCase {
  protected function tearDown(): void {
    $GLOBALS['cife_test_posts'] = array();
    $GLOBALS['cife_test_post_meta'] = array();
    $GLOBALS['cife_test_field_objects'] = array();
    $GLOBALS['cife_test_filters'] = array();
  }

  public function testCollectsLinksFromNestedAcfEditorialFields() {
    $postId = 677;
    $GLOBALS['cife_test_posts'][$postId] = (object) array(
      'ID' => $postId,
      'post_content' => '<a href="https://example.test/body">Body</a>',
    );
    $GLOBALS['cife_test_field_objects'][$postId] = array(
      'dokument' => array(
        'name' => 'dokument',
        'type' => 'repeater',
        'sub_fields' => array(
          array('name' => 'titel', 'type' => 'text'),
          array('name' => 'lank', 'type' => 'url'),
        ),
        'value' => array(
          array('titel' => 'Malmö', 'lank' => 'https://malmo.se/minplats'),
          array('titel' => 'Material', 'lank' => 'https://malmo.se/material.pptx'),
        ),
      ),
      'related_link' => array(
        'name' => 'related_link',
        'type' => 'link',
        'value' => array('url' => 'https://external.test/related'),
      ),
      'extra_content' => array(
        'name' => 'extra_content',
        'type' => 'wysiwyg',
        'value' => '<a href="https://external.test/editorial">Editorial</a>',
      ),
      'resources' => array(
        'name' => 'resources',
        'type' => 'group',
        'sub_fields' => array(
          array('name' => 'download', 'type' => 'file'),
        ),
        'value' => array(
          'download' => array('url' => 'https://example.test/resource.pdf'),
        ),
      ),
      'sections' => array(
        'name' => 'sections',
        'type' => 'flexible_content',
        'layouts' => array(
          array(
            'name' => 'links',
            'sub_fields' => array(
              array('name' => 'destination', 'type' => 'link'),
            ),
          ),
        ),
        'value' => array(
          array(
            'acf_fc_layout' => 'links',
            'destination' => array('url' => 'https://external.test/flexible'),
          ),
        ),
      ),
      'internal_note' => array(
        'name' => 'internal_note',
        'type' => 'text',
        'value' => 'https://private.test/not-rendered',
      ),
    );

    $fragments = (new ContentProvider())->getFragments($postId);
    $urls = (new LinkExtractor())->extract(
      $fragments,
      'https://example.test/page'
    );

    $this->assertSame(array(
      'https://example.test/body',
      'https://malmo.se/minplats',
      'https://malmo.se/material.pptx',
      'https://external.test/related',
      'https://external.test/editorial',
      'https://example.test/resource.pdf',
      'https://external.test/flexible',
    ), $urls);
  }

  public function testAcfFieldsCanBeExcludedByFilter() {
    $postId = 678;
    $GLOBALS['cife_test_posts'][$postId] = (object) array(
      'ID' => $postId,
      'post_content' => '',
    );
    $GLOBALS['cife_test_field_objects'][$postId] = array(
      'private_link' => array(
        'name' => 'private_link',
        'type' => 'url',
        'value' => 'https://private.test/link',
      ),
    );
    $GLOBALS['cife_test_filters']['cife_broken_links_scan_acf_field'] = function (
      $scan,
      $field
    ) {
      return $field['name'] !== 'private_link';
    };

    $fragments = (new ContentProvider())->getFragments($postId);

    $this->assertSame(array(''), $fragments);
  }
}
