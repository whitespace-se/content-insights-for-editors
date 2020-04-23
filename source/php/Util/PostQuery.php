<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;

class PostQuery {
  private static $postTbName = 'post';

  private static function getValueType($value) {
    if (is_float($value)) {
      return '%f';
    }

    if (is_int($value)) {
      return '%d';
    }

    return '%s';
  }

  private static function userSelectQuery() {
    //FIX: When using municipio you may want to filter by "Redaktör" instead of "Author"
    $postName = self::$postTbName;
    if (Settings::getUseAlternateUserField()) {
      global $wpdb;
      return "(SELECT meta_value FROM $wpdb->postmeta WHERE post_id = $postName.ID AND meta_key LIKE 'page_meta_maineditor' AND post.post_type = 'page') = user.ID OR (post.post_author = user.ID AND post.post_type != 'page')";
    }

    return "$postName.post_author = user.ID";
  }

  public static function getListPosts(
    $userID,
    $showOnlyBrokenLinks = false,
    $postId = false,
    $limit = false
  ) {
    global $wpdb;
    $brokenLinksTable = BrokenLinks::$dbTable;
    $analyticsTable = Matomo::getDbTable();
    $postName = self::$postTbName;

    $userSelectQuery = self::userSelectQuery();

    $sql = "SELECT $postName.ID AS ID,
                        $postName.post_title AS title, 
                        $postName.post_name AS url, 
                        COUNT(links.id) AS length,
                        $postName.post_modified AS modified,
                        user.display_name AS author,
                        user.user_email AS author_email,
                        analytics.week_visitors AS week_visitors,
                        analytics.month_visitors AS month_visitors,
                        analytics.updated_date AS updated_date,
                        analytics.week_pageviews AS week_pageviews,
                        analytics.month_pageviews AS month_pageviews
                FROM $wpdb->posts $postName
                INNER JOIN $wpdb->users user ON $userSelectQuery
                LEFT JOIN $brokenLinksTable links ON $postName.ID = links.post_id
                LEFT JOIN $analyticsTable analytics ON $postName.ID = analytics.post_id ";

    $where_posts = apply_filters('cife_posts_where_statments', array());

    if ($postId === false && is_numeric($userID) && $userID != 0) {
      $where_posts['user.ID'] = (int) $userID;
    }

    //Filter by ID
    if (is_numeric($postId)) {
      $where_posts['ID'] = (int) $postId;
    }

    // Array of prepared statements for WHERE clause
    if (count($where_posts)) {
      $where_statments = array();

      foreach ($where_posts as $key => $value) {
        $field = strpos($key, '.') ? $key : $postName . '.' . $key;

        $arg = self::getValueType($value);
        $operator = '=';

        if (gettype($value) == 'array') {
          $operator = 'in';
          $arg =
            '(' .
            join(
              ',',
              array_map(function ($v) {
                return self::getValueType($v);
              }, $value)
            ) .
            ')';
        }

        $where_statments[] = $wpdb->prepare(
          $field . ' ' . $operator . ' ' . $arg,
          $value
        );
      }

      $sql .= ' WHERE ';
      $sql .= implode(' AND ', $where_statments);
    }

    $sql .= ' GROUP BY post.ID';

    if ($showOnlyBrokenLinks) {
      $sql .= ' HAVING length > 0';
    }

    $orderby = !empty($_GET['orderby']) ? esc_sql($_GET['orderby']) : 'length';
    $order = !empty($_GET['order']) ? esc_sql($_GET['order']) : 'DESC';

    $sql .= " ORDER BY $orderby $order";
    if (is_numeric($limit) && $limit > 0) {
      $sql .= " LIMIT $limit";
    }

    $result = $wpdb->get_results($sql);

    return $result;
  }

  public static function getMostVisitedPosts(
    $count = 10,
    $timespan = 'week',
    $desc = true,
    $userID = false
  ) {
    global $wpdb;
    $postName = self::$postTbName;
    $analyticsTable = Matomo::getDbTable();
    $timespanRow = esc_sql(sprintf('%s_visitors', $timespan));
    $sql = "SELECT $postName.ID AS ID,
                        $postName.post_title AS title,
                        COALESCE(analytics.$timespanRow, 0) AS visitors 
				FROM $wpdb->posts $postName";
    if ($userID !== false && Settings::getUseAlternateUserField()) {
      $sql .= " LEFT JOIN $wpdb->postmeta postmeta ON $postName.ID = postmeta.post_id";
    }
    $sql .= " LEFT JOIN $analyticsTable analytics ON $postName.ID = analytics.post_id";
    $sql .= " WHERE $postName.post_status = 'publish' AND $postName.post_type != 'revision'";
    if ($userID !== false) {
      if (Settings::getUseAlternateUserField()) {
        $stmt =
          " AND postmeta.meta_key = 'page_meta_maineditor' AND postmeta.meta_value = %d";
      } else {
        $stmt = " AND $postName.post_author = %d";
      }
      $sql .= $wpdb->prepare($stmt, $userID);
    }

    $order = $desc ? 'DESC' : 'ASC';
    $sql .= $wpdb->prepare(
      " GROUP BY post.ID
				ORDER BY $timespanRow $order
                LIMIT %d",
      $count
    );
    $results = $wpdb->get_results($sql);
    if (class_exists('\CustomerFeedback\App')) {
      foreach ($results as $res) {
        $res->feedback = \CustomerFeedback\Responses::getResponses(
          $res->ID,
          'count'
        );
        $res->feedback[
          'comments'
        ] = \CONTENT_INSIGHTS_FOR_EDITORS\Util\Plugins\CustomerFeedback::getResponsesCommentCount(
          $res->ID
        );
      }
    }
    return $results;
  }

  public static function getMostBrokenLinksPosts($count = 10, $userID = false) {
    global $wpdb;
    $postName = self::$postTbName;
    $brokenLinksTable = BrokenLinks::$dbTable;

    $userSelectQuery = self::userSelectQuery();

    $sql = "SELECT $postName.ID AS ID,
                        $postName.post_title AS title,
                        user.display_name AS author,
                        COUNT(links.id) AS broken_links
				FROM $wpdb->posts $postName";
    $sql .= " INNER JOIN $wpdb->users user ON $userSelectQuery 
				INNER JOIN $brokenLinksTable links ON $postName.ID = links.post_id";
    if ($userID !== false) {
      $sql .= $wpdb->prepare(' WHERE user.ID = %d', $userID);
    }
    $sql .= $wpdb->prepare(
      " GROUP BY post.ID
				HAVING broken_links > 0
                ORDER BY broken_links DESC
                LIMIT 0, %d",
      $count
    );
    return $wpdb->get_results($sql);
  }

  public static function getPagesModifiedAfter(
    $time,
    $take = -1,
    $userID = false
  ) {
    $args = array(
      'post_type' => 'page',
      'posts_per_page' => $take,
      'post_status' => 'publish',
      'date_query' => array(
        'before' => $time,
        'column' => 'post_modified',
      ),
      'order' => 'ASC',
      'orderby' => 'post_modified',
    );
    if ($userID) {
      if (Settings::getUseAlternateUserField()) {
        $args['meta_query'] = $args['meta_query'] ?: array();
        $args['meta_query'][] = array(
          'key' => 'page_meta_maineditor',
          'value' => $userID,
          'compare' => '=',
        );
      } else {
        $args['author'] = $userID;
      }
    }
    $query = new \WP_Query($args);
    $pages = array();
    if ($query->have_posts()) {
      while ($query->have_posts()):
        $query->the_post();
        $page = new \stdClass();
        $page->ID = get_the_ID();
        $page->title = get_the_title();
        $page->lastModified = get_the_modified_date('Y-m-d');
        $pages[] = $page;
      endwhile;
    }
    wp_reset_postdata();
    return $pages;
  }

  public static function getPostWithCustomerFeedbackResponses(
    $count = 10,
    $desc = true,
    $userID = false
  ) {
    // SELECT posts.`ID`, COUNT(*) as count FROM `eslovwp1_postmeta` INNER JOIN `eslovwp1_posts` feedback ON `post_id` = `ID`
    // INNER JOIN `eslovwp1_posts` posts ON `meta_value` = posts.`ID` WHERE `meta_key` = 'customer_feedback_page_reference'
    // AND feedback.`post_date_gmt` > posts.`post_modified_gmt` GROUP BY `meta_value` HAVING count >= 5 ORDER BY count DESC, post_id DESC;
    global $wpdb;
    $postName = self::$postTbName;
    $analyticsTable = Matomo::getDbTable();
    $feedback = 'feedback';

    $userSelectQuery = self::userSelectQuery();

    $sql = "SELECT $postName.ID AS ID,
				$postName.post_title AS title, 
				COUNT(*) as count,
                COALESCE(analytics.week_visitors, 0) AS visitors
                FROM $wpdb->postmeta postmeta
				INNER JOIN $wpdb->posts $feedback ON postmeta.post_id = $feedback.ID 
				INNER JOIN $analyticsTable analytics ON postmeta.meta_value = analytics.post_id 
				INNER JOIN $wpdb->posts $postName ON postmeta.meta_value = $postName.ID";
    if ($userID !== false) {
      $sql .= " INNER JOIN $wpdb->users user ON $userSelectQuery";
    }
    $sql .= " WHERE postmeta.meta_key = 'customer_feedback_page_reference' 
		AND $feedback.post_date_gmt > $postName.post_modified_gmt ";
    if ($userID !== false) {
      $sql .= $wpdb->prepare(' AND user.ID = %d', $userID);
    }
    $sql .=
      " GROUP BY postmeta.meta_value HAVING count >= 5 ORDER BY count DESC;";
    $results = $wpdb->get_results($sql);
    foreach ($results as &$res) {
      $res->feedback = \CustomerFeedback\Responses::getResponses(
        $res->ID,
        'count'
      );
      $res->feedback[
        'comments'
      ] = \CONTENT_INSIGHTS_FOR_EDITORS\Util\Plugins\CustomerFeedback::getResponsesCommentCount(
        $res->ID
      );
    }

    if ($desc === true) {
      usort(
        $results,
        '\CONTENT_INSIGHTS_FOR_EDITORS\Util\PostQuery::sortByResponseFeedbackDESC'
      );
    } else {
      usort(
        $results,
        '\CONTENT_INSIGHTS_FOR_EDITORS\Util\PostQuery::sortByResponseFeedbackASC'
      );
    }
    return array_slice($results, 0, $count);
  }

  public static function sortByResponseFeedbackDESC($a, $b) {
    return self::sortByResponseFeedback($a, $b, 'yes');
  }

  public static function sortByResponseFeedbackASC($a, $b) {
    return self::sortByResponseFeedback($a, $b, 'no');
  }
  public static function sortByResponseFeedback($a, $b, $key = 'yes') {
    $percent =
      ($b->feedback[$key] / $b->count) * 100 -
      ($a->feedback[$key] / $a->count) * 100;
    if ($percent === 1 || $percent === 0) {
      // when 100% or 0% we want the second sorting paramter to be the number of total votes
      return $b->count - $a->count;
    }
    // Give pages with a high number of responses a boost. A page with 0 positive feedbacks and 5 negative will score 100% negative.
    // However a post with 1 positive and 25 negative does actually seem a bit worse than the first one but the score is 96%.
    // Using a logarithmic function that will go between 0 and ~0.12 for x= [0...80] we can slightly push the 1 positive and 25 negative to become higher ranked than 0 positive and 5 negative
    $boost = (1 / 16) * log10($b->count) * 100;
    return round($percent + $boost);
  }
}
