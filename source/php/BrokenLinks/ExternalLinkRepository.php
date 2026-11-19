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
      $host = strtolower(rtrim((string) wp_parse_url($url, PHP_URL_HOST), '.'));
      $sql = $wpdb->prepare(
        "INSERT INTO $links (url,url_hash,host,first_seen,last_seen) VALUES (%s,%s,%s,%s,%s)
         ON DUPLICATE KEY UPDATE url=VALUES(url),host=VALUES(host),last_seen=VALUES(last_seen)",
        $url, $hash, $host, $now, $now
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

  public function saveAutomaticResult($linkId, array $result) {
    global $wpdb;
    $allowedStatuses = array(
      ExternalLinkChecker::OK,
      ExternalLinkChecker::BROKEN,
      ExternalLinkChecker::INCONCLUSIVE,
    );
    $status = isset($result['status']) && in_array($result['status'], $allowedStatuses, true)
      ? $result['status']
      : ExternalLinkChecker::INCONCLUSIVE;
    return $wpdb->update(
      self::linksTableName(),
      array(
        'automatic_checked_at' => current_time('mysql'),
        'automatic_status' => $status,
        'automatic_http_code' => isset($result['http_code']) && is_numeric($result['http_code'])
          ? (int) $result['http_code']
          : null,
        'automatic_message' => isset($result['message'])
          ? substr(sanitize_text_field($result['message']), 0, 255)
          : '',
        'automatic_final_url' => isset($result['final_url'])
          ? substr(esc_url_raw($result['final_url']), 0, 2048)
          : '',
      ),
      array('id' => (int) $linkId),
      array('%s', '%s', '%d', '%s', '%s'),
      array('%d')
    ) !== false;
  }

  public function getNextAutomaticCheck(array $hosts, $intervalDays) {
    global $wpdb;
    $hosts = array_values(array_unique(array_filter(array_map('strval', $hosts))));
    if (!$hosts) {
      return null;
    }
    $placeholders = implode(',', array_fill(0, count($hosts), '%s'));
    $cutoff = (new \DateTimeImmutable('now', wp_timezone()))
      ->modify('-' . max(1, (int) $intervalDays) . ' days')
      ->format('Y-m-d H:i:s');
    $sql = "SELECT DISTINCT link.* FROM " . self::linksTableName() . " link
      INNER JOIN " . self::occurrencesTableName() . " occurrence ON occurrence.link_id=link.id
      INNER JOIN {$wpdb->posts} post ON post.ID=occurrence.post_id
      WHERE post.post_status='publish'
        AND link.host IN ($placeholders)
        AND (link.automatic_checked_at IS NULL OR link.automatic_checked_at<%s)
      ORDER BY COALESCE(link.automatic_checked_at,'1000-01-01 00:00:00') ASC,link.id ASC
      LIMIT 1";
    return $wpdb->get_row($wpdb->prepare($sql, array_merge($hosts, array($cutoff))));
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

  public function getLinks($userId = false, $dueOnly = false, $limit = 50, $offset = 0, $postId = false) {
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
      $sql .= ' AND ' . $this->dueExpression('link') . '<%s';
      $arguments[] = $this->cutoff();
    }
    if ($postId !== false) {
      $sql .= ' AND occurrence.post_id=%d';
      $arguments[] = (int) $postId;
    }
    $sql .= ' ORDER BY ' . $this->dueExpression('link') . ' ASC,link.id ASC LIMIT %d OFFSET %d';
    $arguments[] = max(1, (int) $limit);
    $arguments[] = max(0, (int) $offset);
    $items = $wpdb->get_results($wpdb->prepare($sql, $arguments));
    foreach ($items as $item) {
      $item->pages = $this->getOccurrences($item->id, $userId);
      $reference = $this->reviewReference($item);
      $item->review_due = mysql2date('Y-m-d', $this->addDays($reference), false);
      if ($this->hasUnresolvedAutomaticResult($item)) {
        $item->review_due = mysql2date('Y-m-d', current_time('mysql'), false);
      }
    }
    return $items;
  }

  public function countLinks($userId = false, $dueOnly = false, $postId = false) {
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
      $sql .= ' AND ' . $this->dueExpression('link') . '<%s';
      $arguments[] = $this->cutoff();
    }
    if ($postId !== false) {
      $sql .= ' AND occurrence.post_id=%d';
      $arguments[] = (int) $postId;
    }
    return (int) $wpdb->get_var($arguments ? $wpdb->prepare($sql, $arguments) : $sql);
  }

  public function countForPost($postId) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
      'SELECT COUNT(DISTINCT link_id) FROM ' . self::occurrencesTableName() . ' WHERE post_id=%d',
      $postId
    ));
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

  private function dueExpression($alias) {
    return "CASE
      WHEN $alias.automatic_status IN ('broken','inconclusive')
        AND ($alias.checked_at IS NULL OR $alias.checked_at<$alias.automatic_checked_at)
        THEN '1000-01-01 00:00:00'
      WHEN $alias.automatic_status='ok'
        AND $alias.automatic_checked_at IS NOT NULL
        AND ($alias.checked_at IS NULL OR $alias.checked_at<$alias.automatic_checked_at)
        THEN $alias.automatic_checked_at
      ELSE COALESCE($alias.checked_at,$alias.first_seen)
    END";
  }

  private function reviewReference($item) {
    if (
      isset($item->automatic_status, $item->automatic_checked_at) &&
      $item->automatic_status === ExternalLinkChecker::OK &&
      $item->automatic_checked_at &&
      (!$item->checked_at || $item->checked_at < $item->automatic_checked_at)
    ) {
      return $item->automatic_checked_at;
    }
    return $item->checked_at ?: $item->first_seen;
  }

  private function hasUnresolvedAutomaticResult($item) {
    return isset($item->automatic_status, $item->automatic_checked_at) &&
      in_array(
        $item->automatic_status,
        array(ExternalLinkChecker::BROKEN, ExternalLinkChecker::INCONCLUSIVE),
        true
      ) &&
      $item->automatic_checked_at &&
      (!$item->checked_at || $item->checked_at < $item->automatic_checked_at);
  }

  private function deleteOrphans() {
    global $wpdb;
    return $wpdb->query('DELETE link FROM ' . self::linksTableName() . ' link LEFT JOIN ' .
      self::occurrencesTableName() . ' occurrence ON occurrence.link_id=link.id WHERE occurrence.id IS NULL');
  }
}
