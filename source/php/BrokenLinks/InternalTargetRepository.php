<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class InternalTargetRepository {
  public static function tableName() {
    global $wpdb;
    return $wpdb->prefix . 'cife_internal_link_targets';
  }

  public function remember($url, $postId) {
    global $wpdb;
    $table = self::tableName();
    return $wpdb->query($wpdb->prepare(
      "INSERT INTO $table (url,url_hash,post_id,last_seen) VALUES (%s,%s,%d,%s)
       ON DUPLICATE KEY UPDATE url=VALUES(url),post_id=VALUES(post_id),last_seen=VALUES(last_seen)",
      $url,
      hash('sha256', $url),
      $postId,
      current_time('mysql')
    )) !== false;
  }

  public function wasKnown($url) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare(
      'SELECT id FROM ' . self::tableName() . ' WHERE url_hash=%s LIMIT 1',
      hash('sha256', $url)
    ));
  }
}
