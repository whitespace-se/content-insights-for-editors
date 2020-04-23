<?php
namespace CONTENT_INSIGHTS_FOR_EDITORS\Util\Plugins;

class CustomerFeedback {
  public $postTypeSlug = 'customer-feedback';

  public function __construct() {
    add_action('pre_get_posts', array($this, 'listColumnsSortingQuery'), 50);
  }

  public function listColumnsSortingQuery($query) {
    if (
      !is_admin() ||
      !$query->is_main_query() ||
      $query->get('post_type') != $this->postTypeSlug
    ) {
      return;
    }
    if (!empty($_GET['post_id'])) {
      $postId = intval($_GET['post_id']);
      $metaQuery = $query->get('meta_query');
      $metaQuery[] = array(
        'key' => 'customer_feedback_page_reference',
        'value' => $postId,
        'comare' => '=',
      );
      $query->set('meta_query', $metaQuery);
    }

    if (!empty($_GET['after_date'])) {
      $modDateUnix = intval($_GET['after_date']);
      $modDate = date('Y-m-d H:i:s', $modDateUnix);
      $query->set('date_query', array(
        array(
          'after' => $modDate,
          'inclusive' => true,
        ),
      ));
    }
  }

  public static function getResponsesCommentCount($postId = -1) {
    if (!class_exists('\CustomerFeedback\App')) {
      return 0;
    }
    $modDate = get_the_modified_date('Y-m-d H:i:s', $postId);

    $responses = new \WP_Query(array(
      'posts_per_page' => -1,
      'post_type' => 'customer-feedback',
      'post_status' => array('publish', 'pending', 'draft'),
      'date_query' => array(
        array(
          'after' => $modDate,
          'inclusive' => true,
        ),
      ),
      'meta_query' => array(
        'relation' => 'AND',
        array(
          'key' => 'customer_feedback_page_reference',
          'value' => $postId,
          'comare' => '=',
        ),
        array(
          'key' => 'customer_feedback_comment',
          'compare' => 'EXISTS',
        ),
      ),
    ));
    return $responses->found_posts;
  }
}
