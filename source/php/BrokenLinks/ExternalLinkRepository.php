<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\PostQuery;

class ExternalLinkRepository {
  public static function linksTableName() {
    global $wpdb;
    return $wpdb->prefix . 'cife_external_links';
  }

  public static function occurrencesTableName() {
    global $wpdb;
    return $wpdb->prefix . 'cife_external_link_occurrences';
  }

  public function replaceForPost($postId, array $urls) {
    global $wpdb;
    $links = self::linksTableName();
    $occurrences = self::occurrencesTableName();
    $urls = array_values(array_unique(array_filter($urls, 'is_string')));
    $now = current_time('mysql');
    $linkIds = array();
    $wpdb->query('START TRANSACTION');

    foreach ($urls as $url) {
      $hash = hash('sha256', $url);
      $sql = $wpdb->prepare(
        "INSERT INTO $links (url,url_hash,first_seen,last_seen) VALUES (%s,%s,%s,%s)
         ON DUPLICATE KEY UPDATE url=VALUES(url),last_seen=VALUES(last_seen)",
        $url, $hash, $now, $now
      );
      if ($wpdb->query($sql) === false) {
        $wpdb->query('ROLLBACK');
        return false;
      }
      $linkId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $links WHERE url_hash=%s", $hash));
      if (!$linkId) {
        $wpdb->query('ROLLBACK');
        return false;
      }
      $linkIds[] = $linkId;
      $sql = $wpdb->prepare(
        "INSERT INTO $occurrences (link_id,post_id,first_seen,last_seen) VALUES (%d,%d,%s,%s)
         ON DUPLICATE KEY UPDATE last_seen=VALUES(last_seen)",
        $linkId, $postId, $now, $now
      );
      if ($wpdb->query($sql) === false) {
        $wpdb->query('ROLLBACK');
        return false;
      }
    }

    if ($linkIds) {
      $placeholders = implode(',', array_fill(0, count($linkIds), '%d'));
      $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $occurrences WHERE post_id=%d AND link_id NOT IN ($placeholders)",
        array_merge(array((int) $postId), $linkIds)
      ));
    } else {
      $deleted = $wpdb->delete($occurrences, array('post_id' => $postId), array('%d'));
    }
    if ($deleted === false) {
      $wpdb->query('ROLLBACK');
      return false;
    }
    $wpdb->query('COMMIT');
    $this->deleteOrphans();
    return true;
  }

  public function deleteForPost($postId) {
    global $wpdb;
    $result = $wpdb->delete(self::occurrencesTableName(), array('post_id' => $postId), array('%d'));
    $this->deleteOrphans();
    return $result;
  }

  public function deleteIneligible(array $postTypes, array $postStatuses) {
    global $wpdb;
    if (!$postTypes || !$postStatuses) {
      return false;
    }
    $types = implode(',', array_fill(0, count($postTypes), '%s'));
    $statuses = implode(',', array_fill(0, count($postStatuses), '%s'));
    $sql = "DELETE occurrence FROM " . self::occurrencesTableName() . " occurrence
      LEFT JOIN {$wpdb->posts} post ON post.ID=occurrence.post_id
      WHERE post.ID IS NULL OR post.post_type NOT IN ($types) OR post.post_status NOT IN ($statuses)";
    $result = $wpdb->query($wpdb->prepare($sql, array_merge($postTypes, $postStatuses)));
    $this->deleteOrphans();
    return $result;
  }

  public function markChecked($linkId, $userId) {
    global $wpdb;
    return $wpdb->update(
      self::linksTableName(),
      array('checked_at' => current_time('mysql'), 'checked_by' => (int) $userId),
      array('id' => (int) $linkId),
      array('%s', '%d'), array('%d')
    ) !== false;
  }

  public function userCanReview($linkId, $userId) {
    global $wpdb;
    if (user_can($userId, 'manage_options')) {
      return (bool) $wpdb->get_var($wpdb->prepare(
        'SELECT id FROM ' . self::linksTableName() . ' WHERE id=%d',
        $linkId
      ));
    }
    $ownership = PostQuery::userSelectQuery();
    $sql = "SELECT occurrence.post_id FROM " . self::occurrencesTableName() . " occurrence
      INNER JOIN {$wpdb->posts} post ON post.ID=occurrence.post_id
      INNER JOIN {$wpdb->users} user ON $ownership
      WHERE occurrence.link_id=%d AND user.ID=%d LIMIT 1";
    $postId = (int) $wpdb->get_var($wpdb->prepare($sql, $linkId, $userId));
    return $postId > 0 && user_can($userId, 'edit_post', $postId);
  }

  public function getLinks($userId = false, $dueOnly = false, $limit = 50, $offset = 0) {
    global $wpdb;
    $ownership = PostQuery::userSelectQuery();
    $sql = "SELECT DISTINCT link.* FROM " . self::linksTableName() . " link
      INNER JOIN " . self::occurrencesTableName() . " occurrence ON occurrence.link_id=link.id
      INNER JOIN {$wpdb->posts} post ON post.ID=occurrence.post_id
      INNER JOIN {$wpdb->users} user ON $ownership
      WHERE post.post_status='publish'";
    $arguments = array();
    if ($userId !== false) {
      $sql .= ' AND user.ID=%d';
      $arguments[] = (int) $userId;
    }
    if ($dueOnly) {
      $sql .= ' AND COALESCE(link.checked_at,link.first_seen)<%s';
      $arguments[] = $this->cutoff();
    }
    $sql .= ' ORDER BY COALESCE(link.checked_at,link.first_seen) ASC,link.id ASC LIMIT %d OFFSET %d';
    $arguments[] = max(1, (int) $limit);
    $arguments[] = max(0, (int) $offset);
    $items = $wpdb->get_results($wpdb->prepare($sql, $arguments));
    foreach ($items as $item) {
      $item->pages = $this->getOccurrences($item->id, $userId);
      $item->review_due = mysql2date('Y-m-d', $this->addDays($item->checked_at ?: $item->first_seen), false);
    }
    return $items;
  }

  public function countLinks($userId = false, $dueOnly = false) {
    global $wpdb;
    $ownership = PostQuery::userSelectQuery();
    $sql = "SELECT COUNT(DISTINCT link.id) FROM " . self::linksTableName() . " link
      INNER JOIN " . self::occurrencesTableName() . " occurrence ON occurrence.link_id=link.id
      INNER JOIN {$wpdb->posts} post ON post.ID=occurrence.post_id
      INNER JOIN {$wpdb->users} user ON $ownership WHERE post.post_status='publish'";
    $arguments = array();
    if ($userId !== false) {
      $sql .= ' AND user.ID=%d';
      $arguments[] = (int) $userId;
    }
    if ($dueOnly) {
      $sql .= ' AND COALESCE(link.checked_at,link.first_seen)<%s';
      $arguments[] = $this->cutoff();
    }
    return (int) $wpdb->get_var($arguments ? $wpdb->prepare($sql, $arguments) : $sql);
  }

  public function getDueOccurrencesForUser($userId, $limit = 10) {
    $items = array();
    $userId = $userId === false ? false : (int) $userId;
    foreach ($this->getLinks($userId, true, $limit, 0) as $link) {
      foreach ($link->pages as $page) {
        $items[] = (object) array(
          'link_id' => (int) $link->id, 'url' => $link->url,
          'checked_at' => $link->checked_at, 'first_seen' => $link->first_seen,
          'review_due' => $link->review_due, 'post_id' => (int) $page->post_id,
          'post_title' => $page->post_title,
        );
        if (count($items) >= $limit) {
          return $items;
        }
      }
    }
    return $items;
  }

  private function getOccurrences($linkId, $userId) {
    global $wpdb;
    $ownership = PostQuery::userSelectQuery();
    $sql = "SELECT post.ID AS post_id,post.post_title FROM " . self::occurrencesTableName() . " occurrence
      INNER JOIN {$wpdb->posts} post ON post.ID=occurrence.post_id
      INNER JOIN {$wpdb->users} user ON $ownership
      WHERE occurrence.link_id=%d AND post.post_status='publish'";
    $arguments = array((int) $linkId);
    if ($userId !== false) {
      $sql .= ' AND user.ID=%d';
      $arguments[] = (int) $userId;
    }
    $sql .= ' ORDER BY post.post_title ASC';
    return $wpdb->get_results($wpdb->prepare($sql, $arguments));
  }

  private function cutoff() {
    return (new \DateTimeImmutable('now', wp_timezone()))
      ->modify('-' . Settings::validateAndGetExternalLinkReviewDays() . ' days')->format('Y-m-d H:i:s');
  }

  private function addDays($date) {
    return (new \DateTimeImmutable($date, wp_timezone()))
      ->modify('+' . Settings::validateAndGetExternalLinkReviewDays() . ' days')->format('Y-m-d H:i:s');
  }

  private function deleteOrphans() {
    global $wpdb;
    return $wpdb->query('DELETE link FROM ' . self::linksTableName() . ' link LEFT JOIN ' .
      self::occurrencesTableName() . ' occurrence ON occurrence.link_id=link.id WHERE occurrence.id IS NULL');
  }
}
