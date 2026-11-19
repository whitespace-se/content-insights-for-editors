<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class Repository {
  public static function tableName() {
    global $wpdb;
    return $wpdb->prefix . 'broken_links_detector';
  }

  public function getForPost($postId) {
    global $wpdb;
    $tableName = self::tableName();

    return $wpdb->get_col(
      $wpdb->prepare(
        "SELECT url FROM $tableName WHERE post_id = %d ORDER BY id ASC",
        $postId
      )
    );
  }

  public function replaceForPost($postId, array $urls) {
    global $wpdb;
    $tableName = self::tableName();
    $urls = array_values(array_unique(array_filter($urls, 'is_string')));

    $wpdb->query('START TRANSACTION');

    $deleted = $wpdb->delete($tableName, array('post_id' => $postId), array('%d'));
    if ($deleted === false) {
      $wpdb->query('ROLLBACK');
      return false;
    }

    foreach ($urls as $url) {
      $inserted = $wpdb->insert(
        $tableName,
        array(
          'post_id' => $postId,
          'url' => $url,
        ),
        array('%d', '%s')
      );

      if ($inserted === false) {
        $wpdb->query('ROLLBACK');
        return false;
      }
    }

    $wpdb->query('COMMIT');
    return true;
  }

  public function deleteForPost($postId) {
    global $wpdb;
    return $wpdb->delete(
      self::tableName(),
      array('post_id' => $postId),
      array('%d')
    );
  }

  public function deleteIneligible(array $postTypes, array $postStatuses) {
    global $wpdb;
    if (!$postTypes || !$postStatuses) {
      return false;
    }

    $typePlaceholders = implode(',', array_fill(0, count($postTypes), '%s'));
    $statusPlaceholders = implode(',', array_fill(0, count($postStatuses), '%s'));
    $arguments = array_merge($postTypes, $postStatuses);
    $sql = "DELETE links FROM " . self::tableName() . " links
      LEFT JOIN {$wpdb->posts} post ON post.ID = links.post_id
      WHERE post.ID IS NULL
        OR post.post_type NOT IN ($typePlaceholders)
        OR post.post_status NOT IN ($statusPlaceholders)";

    return $wpdb->query($wpdb->prepare($sql, $arguments));
  }
}
