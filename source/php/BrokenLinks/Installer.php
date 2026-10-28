<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class Installer {
  const DB_VERSION = '2.0.0';
  const DB_VERSION_OPTION = 'cife_broken_links_db_version';

  public static function install() {
    global $wpdb;

    $tableName = Repository::tableName();
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

    update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
  }

  public static function maybeInstall() {
    global $wpdb;

    $tableName = Repository::tableName();
    $tableExists = $wpdb->get_var(
      $wpdb->prepare('SHOW TABLES LIKE %s', $tableName)
    );

    if (
      $tableExists !== $tableName ||
      get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION
    ) {
      self::install();
    }
  }
}
