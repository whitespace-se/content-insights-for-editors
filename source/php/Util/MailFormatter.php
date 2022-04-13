<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;

class MailFormatter {
  public static function mapArrayBase($post) {
    return [
      'ID' => $post->ID,
      'post' => $post,
      'url' => get_edit_post_link($post->ID),
      'title' => get_the_title($post->ID),
    ];
  }
  public static function mapLastModifiedValue($item) {
    $item['value'] = get_the_modified_date('Y-m-d', $item['ID']);
    return $item;
  }

  public static function mapNumberOfViews($item) {
    $item['value'] = $item['post']->visitors;
    if (class_exists('\CustomerFeedback\App')) {
      $item['feedback'] = [
        'yes' => round($item['post']->feedback['yes']) . '%',
        'no' => round($item['post']->feedback['no']) . '%',
      ];
    }
    return $item;
  }

  public static function formatAndSendMail($userID) {
    $logo = apply_filters('cife_notification_mail_logo_url', null);
    $userData = get_userdata($userID);
    if (class_exists('\BrokenLinkDetector\App')):
      $brokenLinks = PostQuery::getListPosts($userID, true);
      $brokenLinksMapped = array_map(
        '\CONTENT_INSIGHTS_FOR_EDITORS\Util\MailFormatter::mapArrayBase',
        $brokenLinks
      );
    endif;
    $last_updated_threshold = Settings::validateAndGetLastUpdatedThreshold();
    $time = sprintf('-%d days', $last_updated_threshold);
    $rarelyUpdated = PostQuery::getPagesModifiedAfter($time, 10, $userID);
    $rarelyUpdatedMapped = array_map(
      '\CONTENT_INSIGHTS_FOR_EDITORS\Util\MailFormatter::mapArrayBase',
      $rarelyUpdated
    );
    $rarelyUpdatedMapped = array_map(
      '\CONTENT_INSIGHTS_FOR_EDITORS\Util\MailFormatter::mapLastModifiedValue',
      $rarelyUpdatedMapped
    );

    if (Matomo::$matomoIsActive):
      $mostViewed = PostQuery::getMostVisitedPosts(10, 'week', true, $userID);
      $mostViewedMapped = array_map(
        '\CONTENT_INSIGHTS_FOR_EDITORS\Util\MailFormatter::mapArrayBase',
        $mostViewed
      );
      $mostViewedMapped = array_map(
        '\CONTENT_INSIGHTS_FOR_EDITORS\Util\MailFormatter::mapNumberOfViews',
        $mostViewedMapped
      );
    endif;

    $_htmlvars = [
      'logo' => $logo,
      'intro_header' => sprintf(
        '%s %s,',
        __('Hi', 'content-insights-for-editors'),
        $userData->first_name ?: $userData->nicename
      ),
      'intro_text' => __(
        'This is a short summary of all pages you are the author to that contains broken links, pages that has not been updated in a while and your most visited pages this week.',
        'content-insights-for-editors'
      ),
    ];

    $sections = array();
    if (class_exists('\BrokenLinkDetector\App')):
      $sections[] = [
        'id' => 'broken-links',
        'list' => $brokenLinksMapped,
        'list_header' => [
          'title' => __('Page title', 'content-insights-for-editors'),
        ],
        'section_header' => __('Broken links', 'content-insights-for-editors'),
        'no_items_text' => __(
          'Good news! No broken links found this time, keep up the good work.',
          'content-insights-for-editors'
        ),
      ];
    endif;
    if (Matomo::$matomoIsActive) {
      $sections[] = [
        'id' => 'most-viewed',
        'list' => $mostViewedMapped,
        'list_header' => [
          'title' => __('Page title', 'content-insights-for-editors'),
          'value' => __('Number of visitors', 'content-insights-for-editors'),
        ],
        'section_header' => __(
          'Your top 10 pages most visited last week',
          'content-insights-for-editors'
        ),
        'no_items_text' => __(
          'Seems like you are not the author of any pages yet.',
          'content-insights-for-editors'
        ),
      ];
    }
    $sections[] = [
      'id' => 'rarely-updated',
      'list' => $rarelyUpdatedMapped,
      'list_header' => [
        'title' => __('Page title', 'content-insights-for-editors'),
        'value' => __('Last updated', 'content-insights-for-editors'),
      ],
      'section_header' => __(
        'Pages not updated in a while',
        'content-insights-for-editors'
      ),
      'no_items_text' => __(
        'Excellent all your pages have been updated recently!',
        'content-insights-for-editors'
      ),
    ];

    $_htmlvars['sections'] = apply_filters(
      'cife_notification_mail_list_sections',
      $sections
    );
    if (class_exists('\CustomerFeedback\App')) {
      foreach ($_htmlvars['sections'] as &$section) {
        if ($section['id'] === 'most-viewed') {
          $section['list_header']['feedback'] = __(
            'Customer feedback',
            'content-insights-for-editors'
          );
        }
      }
    }

    $_htmlvars = apply_filters('cife_notification_mail_vars', $_htmlvars);

    $email_to = $userData->user_email;
    $email_subject = __(
      'Summary of your pages content',
      'content-insights-for-editors'
    );
    $email_body = include CONTENT_INSIGHTS_FOR_EDITORS_PATH .
      '/mail-templates/broken-links-notice.template.php';
    $headers = array('Content-Type: text/html; charset=UTF-8');
    return wp_mail($email_to, $email_subject, $email_body, $headers);
  }
}
