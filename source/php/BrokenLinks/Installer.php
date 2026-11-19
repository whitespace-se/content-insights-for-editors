<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class Installer {
  const DB_VERSION = '4.0.0';
  const DB_VERSION_OPTION = 'cife_broken_links_db_version';

  public static function install() {
    global $wpdb;

    $tableName = Repository::tableName();
    $externalLinksTable = ExternalLinkRepository::linksTableName();
    $occurrencesTable = ExternalLinkRepository::occurrencesTableName();
    $internalTargetsTable = InternalTargetRepository::tableName();
    $charsetCollation = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tableName (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      post_id bigint(20) DEFAULT NULL,
      url varchar(2048) DEFAULT '' NOT NULL,
      time timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      KEY post_id (post_id)
    ) $charsetCollation;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    dbDelta("CREATE TABLE $externalLinksTable (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      url varchar(2048) DEFAULT '' NOT NULL,
      url_hash char(64) DEFAULT '' NOT NULL,
      host varchar(255) DEFAULT '' NOT NULL,
      first_seen datetime NOT NULL,
      last_seen datetime NOT NULL,
      checked_at datetime DEFAULT NULL,
      checked_by bigint(20) DEFAULT NULL,
      automatic_checked_at datetime DEFAULT NULL,
      automatic_status varchar(20) DEFAULT NULL,
      automatic_http_code smallint DEFAULT NULL,
      automatic_message varchar(255) DEFAULT '' NOT NULL,
      automatic_final_url varchar(2048) DEFAULT '' NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY url_hash (url_hash),
      KEY host (host),
      KEY checked_at (checked_at)
    ) $charsetCollation;");
    dbDelta("CREATE TABLE $occurrencesTable (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      link_id bigint(20) NOT NULL,
      post_id bigint(20) NOT NULL,
      first_seen datetime NOT NULL,
      last_seen datetime NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY link_post (link_id,post_id),
      KEY post_id (post_id)
    ) $charsetCollation;");
    dbDelta("CREATE TABLE $internalTargetsTable (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      url varchar(2048) DEFAULT '' NOT NULL,
      url_hash char(64) DEFAULT '' NOT NULL,
      post_id bigint(20) NOT NULL,
      last_seen datetime NOT NULL,
      PRIMARY KEY  (id),
      UNIQUE KEY url_hash (url_hash),
      KEY post_id (post_id)
    ) $charsetCollation;");

    self::backfillExternalLinkHosts($externalLinksTable);

    update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
  }

  private static function backfillExternalLinkHosts($tableName) {
    global $wpdb;
    $lastId = 0;
    do {
      $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id,url FROM $tableName WHERE host='' AND id>%d ORDER BY id ASC LIMIT 500",
        $lastId
      ));
      foreach ($rows as $row) {
        $lastId = (int) $row->id;
        $host = strtolower(rtrim((string) wp_parse_url($row->url, PHP_URL_HOST), '.'));
        $wpdb->update(
          $tableName,
          array('host' => $host),
          array('id' => (int) $row->id),
          array('%s'),
          array('%d')
        );
      }
    } while (count($rows) === 500);
  }

  public static function maybeInstall() {
    global $wpdb;

    $tables = array(
      Repository::tableName(),
      ExternalLinkRepository::linksTableName(),
      ExternalLinkRepository::occurrencesTableName(),
      InternalTargetRepository::tableName(),
    );
    $missingTable = false;
    foreach ($tables as $tableName) {
      if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName)) !== $tableName) {
        $missingTable = true;
        break;
      }
    }

    if (
      $missingTable ||
      get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION
    ) {
      self::install();
    }
  }
}
