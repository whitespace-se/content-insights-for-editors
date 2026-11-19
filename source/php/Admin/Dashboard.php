<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;

use CONTENT_INSIGHTS_FOR_EDITORS\Util\PostQuery;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\Matomo;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkRepository;

class Dashboard {
  function __construct() {
    add_action('wp_dashboard_setup', array($this, 'addDashboardWidgets'));
  }

  public function addDashboardWidgets() {
    global $wp_meta_boxes;
    $last_updated_threshold = Settings::validateAndGetLastUpdatedThreshold();
    $minimum_feedback_responses_threshold = Settings::validateAndGetMinFeedbackResponsesThreshold();
    if (current_user_can('manage_options')):
      if (Matomo::$matomoIsActive):
        wp_add_dashboard_widget(
          'cife_visitors_week_admin',
          __(
            'Top 10 most visitied pages last 7 days (sitewide)',
            'content-insights-for-editors'
          ),
          array($this, 'adminTopTenLastWeek')
        );
        wp_add_dashboard_widget(
          'cife_visitors_month_admin',
          __(
            'Top 10 most visitied pages last month (sitewide)',
            'content-insights-for-editors'
          ),
          array($this, 'adminTopTenLastMonth')
        );
      endif;
      wp_add_dashboard_widget(
        'cife_broken_links_admin',
        __(
          'Top 10 pages with most number of broken links (sitewide)',
          'content-insights-for-editors'
        ),
        array($this, 'adminTopTenBrokenLinks')
      );
      wp_add_dashboard_widget(
        'cife_external_links_due_admin',
        __('External links requiring review (sitewide)', 'content-insights-for-editors'),
        array($this, 'adminExternalLinksDue')
      );
      if ($last_updated_threshold) {
        wp_add_dashboard_widget(
          'cife_rarely_updated_admin',
          sprintf(
            '%s',
            __(
              'Top 10 pages not updated in a while (sitewide)',
              'content-insights-for-editors'
            )
          ),
          array($this, 'adminRarelyUpdated')
        );
      }
    endif;

    if (Matomo::$matomoIsActive):
      wp_add_dashboard_widget(
        'cife_visitors_top_month_user',
        __(
          'Your top 10 pages most visited last month',
          'content-insights-for-editors'
        ),
        array($this, 'userTopTenLastMonth')
      );

      wp_add_dashboard_widget(
        'cife_visitors_bottom_month_user',
        __(
          'Your 10 least visited pages last month',
          'content-insights-for-editors'
        ),
        array($this, 'userBottomLastMonth')
      );
    endif;

    wp_add_dashboard_widget(
      'cife_broken_links_user',
      __('All your pages with broken links', 'content-insights-for-editors'),
      array($this, 'userBrokenLinks')
    );
    if (current_user_can('edit_posts')) {
      wp_add_dashboard_widget(
        'cife_high_performance_risk',
        __('Pages with high performance risk', 'content-insights-for-editors'),
        array($this, 'highPerformanceRisk')
      );
      wp_add_dashboard_widget(
        'cife_external_links_due_user',
        __('External links on your pages requiring review', 'content-insights-for-editors'),
        array($this, 'userExternalLinksDue')
      );
    }

    if (class_exists('\CustomerFeedback\App')):
      wp_add_dashboard_widget(
        'cife_customer_responses_top_user',
        sprintf(
          '%s (%s %d %s)',
          __(
            'Top 10 pages with most most positive feedback responses',
            'content-insights-for-editors'
          ),
          __('minimum', 'content-insights-for-editors'),
          $minimum_feedback_responses_threshold,
          __('votes', 'content-insights-for-editors')
        ),
        array($this, 'userTopCustomerResponses')
      );
      wp_add_dashboard_widget(
        'cife_customer_responses_bottom_user',
        sprintf(
          '%s (%s %d %s)',
          __(
            'Top 10 pages with least positive feedback responses',
            'content-insights-for-editors'
          ),
          __('minimum', 'content-insights-for-editors'),
          $minimum_feedback_responses_threshold,
          __('votes', 'content-insights-for-editors')
        ),
        array($this, 'userBottomCustomerResponses')
      );
    endif;

    if ($last_updated_threshold) {
      wp_add_dashboard_widget(
        'cife_rarely_updated_user',
        sprintf(
          '%s %d %s',
          __(
            'All your pages not updated in the last',
            'content-insights-for-editors'
          ),
          $last_updated_threshold,
          __('days', 'content-insights-for-editors')
        ),
        array($this, 'userRarelyUpdated')
      );
    }
  }

  private function validateAndGetLastUpdatedThreshold() {
    $threshold = get_field('ws_minimum_last_updated_threshold', 'options');
    return is_numeric($threshold) && $threshold > 0 ? $threshold : false;
  }

  /*
		Visitors section
	*/
  public function adminTopTenLastWeek() {
    $this->renderMostViewed(10, 'week');
  }

  public function adminTopTenLastMonth() {
    $this->renderMostViewed(10, 'month');
  }

  public function userTopTenLastMonth() {
    $this->renderMostViewed(10, 'month', true, get_current_user_id());
  }

  public function userBottomLastMonth() {
    $this->renderMostViewed(10, 'month', false, get_current_user_id());
  }

  /*
		Broken links section
	*/
  public function adminTopTenBrokenLinks() {
    $this->renderBrokenLinks(10, false);
  }

  public function userBrokenLinks() {
    $this->renderBrokenLinks(1000, get_current_user_id());
  }

  public function adminExternalLinksDue() {
    $this->renderExternalLinksDue(false, 10);
  }

  public function userExternalLinksDue() {
    $this->renderExternalLinksDue(get_current_user_id(), 100);
  }

  public function highPerformanceRisk() {
    $userId = current_user_can('manage_options') ? false : get_current_user_id();
    $pages = PostQuery::getHighPerformanceRiskPosts(10, $userId);
    if (!$pages) {
      echo '<p><span class="dashicons dashicons-yes-alt cife-performance__empty-icon"></span> ' .
        esc_html__('No high-risk pages right now.', 'content-insights-for-editors') . '</p>';
      return;
    }

    echo '<table class="cife_table"><tbody>';
    foreach ($pages as $page) {
      echo '<tr><td><a href="' . esc_url(get_edit_post_link($page->ID)) . '"><strong>' .
        esc_html($page->title) . '</strong></a></td><td class="cife-performance__risk-cell">' .
        '<span class="cife-performance__badge cife-performance__badge--high">' .
        esc_html__('High', 'content-insights-for-editors') . '</span></td></tr>';
    }
    echo '</tbody></table>';
    echo '<p><a href="' . esc_url(admin_url('admin.php?page=content-insights-for-editors-page')) . '">' .
      esc_html__('View all analysed pages', 'content-insights-for-editors') . '</a></p>';
  }

  public function renderExternalLinksDue($userId, $take) {
    $items = (new ExternalLinkRepository())->getDueOccurrencesForUser($userId, $take);
    if (!$items) {
      echo '<p>' . esc_html__('No external links currently require review.', 'content-insights-for-editors') . '</p>';
      return;
    }
    echo '<table class="cife_table"><tbody>';
    foreach ($items as $item) {
      echo '<tr><td><a target="_blank" rel="noopener noreferrer" href="' . esc_url($item->url) . '">' . esc_html($item->url) . '</a><br>';
      echo '<a href="' . esc_url(get_edit_post_link($item->post_id)) . '">' . esc_html($item->post_title) . '</a></td>';
      echo '<td>' . esc_html($item->review_due) . '</td></tr>';
    }
    echo '</tbody></table><p><a href="' . esc_url(admin_url('admin.php?page=' . ExternalLinks::PAGE_SLUG . '&review_due=1')) . '">' . esc_html__('Review external links', 'content-insights-for-editors') . '</a></p>';
  }

  public function userRarelyUpdated() {
    $this->renderRarelyUpdated(-1, get_current_user_id());
  }

  public function adminRarelyUpdated() {
    $this->renderRarelyUpdated(10);
  }

  /*
		Customer responses
	*/

  public function userTopCustomerResponses() {
    $this->renderCustomerResponses(10, get_current_user_id());
  }

  public function userBottomCustomerResponses() {
    $this->renderCustomerResponses(10, get_current_user_id(), false);
  }

  /*
		Render section
	*/
  public function renderBrokenLinks($take, $userID) {
    $pages = PostQuery::getMostBrokenLinksPosts($take, $userID);
    if (count($pages) > 0):
      echo "<table class='cife_table'>";
      foreach ($pages as $value) {
        echo '<tr>
                    <td>
                        <a href="' .
          esc_url(get_edit_post_link($value->ID)) .
          '"><strong>' .
          esc_html($value->title) .
          '</strong></a>
                    </td>
                    <td>' .
          esc_html($value->author) .
          '</td>
                    <td>' .
          absint($value->broken_links) .
          '</td>
                </tr>';
      }
      echo '</table>';
    else:
      echo sprintf(
        '<h2>%s <span class="dashicons dashicons-yes" style="color: green; font-size: 1.3em;"></span></h2>',
        __('No broken links found', 'content-insights-for-editors')
      );
      echo sprintf(
        '<p>%s</p>',
        __(
          'Nice job, everything seems to be in order.',
          'content-insights-for-editors'
        )
      );
      echo sprintf(
        '<p>%s <a href="%s">%s</a></p>',
        __('Click here to', 'content-insights-for-editors'),
        esc_url(admin_url('admin.php?page=content-insights-for-editors-page')),
        __('view your pages', 'content-insights-for-editors')
      );
    endif;
  }

  /**
   *
   * @param [int] $take
   * @param [string] $timespan type of time (week or month)
   * @param [bool|int] $userID
   * @return void
   */
  public function renderMostViewed(
    $take,
    $timespan,
    $desc = true,
    $userID = false
  ) {
    $pages = PostQuery::getMostVisitedPosts($take, $timespan, $desc, $userID);
    if (count($pages) > 0):
      echo "<table class='cife_table'>";
      echo sprintf(
        '<thead>
				<tr>
					<th style="text-align: left;">%s</th>
					<th>%s</th>
					<th>%s</th>
				</tr>
				</thead>',
        __('Page title', 'content-insights-for-editors'),
        __('Number of visitors', 'content-insights-for-editors'),
        __('Last updated', 'content-insights-for-editors')
      );
      echo '<tbody>';
      foreach ($pages as $value) {
        echo '<tr>
                    <td>
                        <a href="' .
          esc_url(get_edit_post_link($value->ID)) .
          '"><strong>' .
          esc_html($value->title) .
          '</strong></a>';
        if (isset($value->feedback)):
          echo '<div class="cife-customer-feedback-wrapper">
					<span class="cife-customer-feedback cife-customer-feedback--positive">' .
            esc_html(round($value->feedback['yes'])) .
            '<i class="cife-icon cife-icon--thumb-up"></i></span>
					<span class="cife-customer-feedback cife-customer-feedback--negative">' .
            esc_html(round($value->feedback['no'])) .
            '<i class="cife-icon cife-icon--thumb-down"></i></span>
					<span class="cife-customer-feedback cife-customer-feedback--comments">' .
            absint($value->feedback['comments']) .
            '<i class="cife-icon cife-icon--comments"></i>';
          if ($value->feedback['comments'] > 0):
            echo '<a href="' .
              esc_url(admin_url(
                add_query_arg(
                  array(
                    'post_id' => $value->ID,
                    'has-comment' => 'yes',
                    'after_date' => get_post_modified_time(
                      'U',
                      true,
                      $value->ID
                    ),
                  ),
                  'edit.php?post_type=customer-feedback'
                )
              )) .
              '" target="_blank">Visa kommentarer</a>';
          endif;
          echo '</span>
					</div>';
        endif;
        echo '</td>
                    <td>' .
          absint($value->visitors) .
          '</td>
					<td>' .
          esc_html(get_the_modified_date('Y-m-d', $value->ID)) .
          '</td>
                </tr>';
      }
      echo '</tbody></table>';
    else:
      echo sprintf(
        '<h2>%s</h2>',
        __('No pages found', 'content-insights-for-editors')
      );
      if ($userID) {
        echo sprintf(
          '<p>%s %s <a href="%s">%s</a></p>',
          __(
            'Seems like you are not the author of any pages yet.',
            'content-insights-for-editors'
          ),
          __('Click here to', 'content-insights-for-editors'),
          esc_url(admin_url('post-new.php?post_type=page')),
          __('create a new page', 'content-insights-for-editors')
        );
      }
    endif;
  }

  public function renderRarelyUpdated($take, $userID = false) {
    $last_updated_threshold = Settings::validateAndGetLastUpdatedThreshold();
    $time = sprintf('-%d days', $last_updated_threshold);
    $pages = PostQuery::getPagesModifiedAfter($time, $take, $userID);
    if (count($pages) > 0):
      echo "<table class='cife_table'>";
      echo sprintf(
        '<thead>
				<tr>
					<th style="text-align: left;">%s</th>
					<th style="width: 50%%; text-align: right;">%s</th>
				</tr>
				</thead>',
        __('Page title', 'content-insights-for-editors'),
        __('Last updated', 'content-insights-for-editors')
      );
      echo '<tbody>';
      foreach ($pages as $page) {
        echo '<tr>
                    <td>
                        <a href="' .
          esc_url(get_edit_post_link($page->ID)) .
          '"><strong>' .
          esc_html($page->title) .
          '</strong></a>
					</td>
					<td style="width: 30%; text-align: right;">' .
          esc_html($page->lastModified) .
          '</td>
                </tr>';
      }
      echo '</tbody></table>';
    else:
      echo sprintf(
        '<h2>%s <span class="dashicons dashicons-yes" style="color: green; font-size: 1.3em;"></span></h2>',
        __('No old pages found', 'content-insights-for-editors')
      );
      if ($userID) {
        echo '<p>' .
          sprintf(
            /* translators: %d: number of days in the outdated-content threshold. */
            __(
              'Great! All of your pages have been updated within the last %d days.',
              'content-insights-for-editors'
            ),
            $last_updated_threshold
          ) .
          '</p>';
        echo sprintf(
          '<p>%s <a href="%s">%s</a></p>',
          __('Click here to', 'content-insights-for-editors'),
          esc_url(admin_url('admin.php?page=content-insights-for-editors-page')),
          __('view your pages', 'content-insights-for-editors')
        );
      }
    endif;
  }

  public function renderCustomerResponses(
    $take = 10,
    $userID = false,
    $desc = true
  ) {
    $pages = PostQuery::getPostWithCustomerFeedbackResponses(
      $take,
      $desc,
      $userID
    );
    if (count($pages) > 0):
      echo "<table class='cife_table'>";
      echo sprintf(
        '<thead>
				<tr>
					<th style="text-align: left;">%s</th>
					<th>%s</th>
				</tr>
				</thead>',
        __('Page title', 'content-insights-for-editors'),
        __('Weekly visitors', 'content-insights-for-editors')
      );
      echo '<tbody>';
      foreach ($pages as $value) {
        echo '<tr>
                    <td>
                        <a href="' .
          esc_url(get_edit_post_link($value->ID)) .
          '"><strong>' .
          esc_html($value->title) .
          '</strong></a>';
        if (isset($value->feedback)):
          echo '<div class="cife-customer-feedback-wrapper">
					<span class="cife-customer-feedback cife-customer-feedback--positive">' .
            esc_html(round($value->feedback['yes'])) .
            '<i class="cife-icon cife-icon--thumb-up"></i></span>
					<span class="cife-customer-feedback cife-customer-feedback--negative">' .
            esc_html(round($value->feedback['no'])) .
            '<i class="cife-icon cife-icon--thumb-down"></i></span>
					<span class="cife-customer-feedback cife-customer-feedback--comments">' .
            absint($value->feedback['comments']) .
            '<i class="cife-icon cife-icon--comments"></i>';
          if ($value->feedback['comments'] > 0):
            echo '<a href="' .
              esc_url(admin_url(
                add_query_arg(
                  array(
                    'post_id' => $value->ID,
                    'has-comment' => 'yes',
                    'after_date' => get_post_modified_time(
                      'U',
                      true,
                      $value->ID
                    ),
                  ),
                  'edit.php?post_type=customer-feedback'
                )
              )) .
              '" target="_blank">Visa kommentarer</a>';
          endif;
          echo '</span>
					</div>';
        endif;
        echo '</td>
                    <td>' .
          absint($value->visitors) .
          '</td>
                </tr>';
      }
      echo '</tbody></table>';
    else:
      echo sprintf(
        '<h2>%s</h2>',
        __('No pages found', 'content-insights-for-editors')
      );
      if ($userID) {
        echo sprintf(
          '<p>%s</p>',
          __(
            'We could not find any pages that has got enough votes',
            'content-insights-for-editors'
          )
        );
      }
    endif;
  }
}
