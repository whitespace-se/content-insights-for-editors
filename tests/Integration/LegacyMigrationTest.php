<?php

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Installer;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Repository;

class LegacyMigrationTest extends WP_UnitTestCase {
  public function testInstallerAdoptsPopulatedLegacyTableWithoutDataLoss() {
    global $wpdb;
    $tableName = Repository::tableName();

    $wpdb->query("DROP TABLE IF EXISTS $tableName");
    $wpdb->query(
      "CREATE TABLE $tableName (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) DEFAULT NULL,
        url varchar(255) DEFAULT '' NOT NULL,
        time timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY id (id)
      ) {$wpdb->get_charset_collate()}"
    );
    $wpdb->insert(
      $tableName,
      array('post_id' => 123, 'url' => 'https://broken.example/'),
      array('%d', '%s')
    );

    Installer::install();

    $this->assertSame(
      'https://broken.example/',
      $wpdb->get_var("SELECT url FROM $tableName WHERE post_id = 123")
    );
  }

  public function testRepositoryReplacesAndDeduplicatesPostResults() {
    Installer::install();
    $repository = new Repository();

    $this->assertTrue(
      $repository->replaceForPost(
        456,
        array('https://one.example/', 'https://one.example/', 'https://two.example/')
      )
    );

    $this->assertSame(
      array('https://one.example/', 'https://two.example/'),
      $repository->getForPost(456)
    );
  }

  public function testRepositoryStoresLongUrlsWithoutTruncation() {
    Installer::install();
    $repository = new Repository();
    $url = 'https://example.test/path?' . str_repeat('parameter=value&', 80);

    $this->assertTrue($repository->replaceForPost(789, array($url)));
    $this->assertSame(array($url), $repository->getForPost(789));
  }

  public function testRepositoryRemovesResultsForIneligiblePosts() {
    Installer::install();
    $repository = new Repository();
    $publishedPage = self::factory()->post->create(
      array('post_type' => 'page', 'post_status' => 'publish')
    );
    $draftPage = self::factory()->post->create(
      array('post_type' => 'page', 'post_status' => 'draft')
    );
    $publishedPost = self::factory()->post->create(
      array('post_type' => 'post', 'post_status' => 'publish')
    );
    $repository->replaceForPost($publishedPage, array('https://one.example/'));
    $repository->replaceForPost($draftPage, array('https://two.example/'));
    $repository->replaceForPost($publishedPost, array('https://three.example/'));

    $repository->deleteIneligible(array('page'), array('publish'));

    $this->assertSame(array('https://one.example/'), $repository->getForPost($publishedPage));
    $this->assertSame(array(), $repository->getForPost($draftPage));
    $this->assertSame(array(), $repository->getForPost($publishedPost));
  }
}
