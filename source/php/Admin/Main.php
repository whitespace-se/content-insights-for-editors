<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;

use CONTENT_INSIGHTS_FOR_EDITORS\Util\PostQuery;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\MailFormatter;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\BrokenLinks;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\ListTable;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\Matomo;

class Main {
  public static $MENU_SLUG = 'content-insights-for-editors-page';

  public function __construct() {
    add_action('admin_menu', array($this, 'init'));

    add_action('wp_ajax_trigger_update_analytics', array(
      $this,
      'triggerUpdateAnalytics',
    ));

    add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));

    add_filter('cife_posts_where_statments', array($this, 'addGetValues'));
  }

  public function init() {
    add_menu_page(
      __('Content Insights for Editors', 'content-insights-for-editors'),
      __('Content Insights for Editors', 'content-insights-for-editors'),
      'edit_posts',
      self::$MENU_SLUG,
      array($this, 'render')
    );
    add_submenu_page(
      self::$MENU_SLUG,
      __('Notifications', 'content-insights-for-editors'),
      __('Notifications', 'content-insights-for-editors'),
      'manage_options',
      self::$MENU_SLUG . '-notifications',
      array($this, 'renderNotifications')
    );
  }

  public function addGetValues($where_posts) {
    $where_posts['post_status'] = 'publish';

    return $where_posts;
  }

  public function renderNotifications() {
    $this->handleNotificationSubmit();

    $nonce = wp_create_nonce('cife_notify_nonce');
    echo '<div class="wrap nestedpages">';
    echo sprintf(
      '<h1>%s</h1>',
      __(
        'Send a notice about their broken links to selected users',
        'content-insights-for-editors'
      )
    );
    echo '<form method="post">';
    echo sprintf(
      '<input type="hidden" name="cife_notify_nonce" value="%s">',
      esc_attr($nonce)
    );
    $selectionHTML = wp_dropdown_users(array(
      'role__in' => ['administrator', 'editor', 'author'],
      'orderby' => 'display_name',
      'order' => 'ASC',
      'show' => 'display_name',
      'echo' => false,
      'include_selected' => true,
      'name' => 'cife_notify_authors[]',
    ));
    $selectionHTML = preg_replace(
      '/<select(.*?)>/',
      '<select $1 size="25" multiple>',
      $selectionHTML
    );
    echo $selectionHTML;
    echo submit_button(
      __('Send email to selected users', 'content-insights-for-editors'),
      'button',
      'cife_notify'
    );
    echo '</form>';
    echo '</div>';
  }

  public function handleNotificationSubmit() {
    if (isset($_POST['cife_notify'])) {
      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to send these notifications.', 'content-insights-for-editors'));
      }
      $nonce = isset($_POST['cife_notify_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['cife_notify_nonce']))
        : '';
      if (wp_verify_nonce($nonce, 'cife_notify_nonce')) {
        if (isset($_POST['cife_notify_authors'])) {
          $authors = array_values(
            array_filter(
              array_unique(
                array_map('absint', (array) wp_unslash($_POST['cife_notify_authors']))
              ),
              'get_userdata'
            )
          );
          $successful = 0;
          foreach ($authors as $id) {
            if (MailFormatter::formatAndSendMail($id)) {
              $successful++;
            }
          }
          echo sprintf(
            '<h1>%s %d %s %d %s</h1>',
            __('Sent', 'content-insights-for-editors'),
            $successful,
            __('of', 'content-insights-for-editors'),
            count($authors),
            __('emails', 'content-insights-for-editors')
          );
        } else {
          echo sprintf(
            '<h1>%s</h1>',
            __(
              'No mail was sent, please select at least one user',
              'content-insights-for-editors'
            )
          );
        }
      }
    }
  }

  public function render() {
    $PageList = new ListTable();

    echo '<div class="wrap nestedpages">';
    if (
      isset($_GET['cife_scan_started']) &&
      sanitize_key(wp_unslash($_GET['cife_scan_started'])) === '1'
    ) {
      echo '<div class="notice notice-success is-dismissible"><p>';
      _e(
        'The broken-link check has been scheduled and will continue in the background.',
        'content-insights-for-editors'
      );
      echo '</p></div>';
    }
    $this->renderTableHeader();
    $PageList->prepare_items();
    $PageList->display();
    echo '</div>';
  }

  private function renderTableHeader() {
    $nextRun = '';
    if (
      !is_null(BrokenLinks::$nextScheduledRun) &&
      !empty(BrokenLinks::$nextScheduledRun)
    ) {
      $nextRun = '<p>';
      $nextRun .= sprintf(
        '%s: %s',
        __(
          'Next scheduled check for broken links',
          'content-insights-for-editors'
        ),
        esc_html(BrokenLinks::$nextScheduledRun)
      );
      $nextRun .= '</p>';
    }

    echo '<div class="nestedpages-listing-title">';
    echo sprintf(
      '<h1>%s</h1>',
      __('Analysed pages', 'content-insights-for-editors')
    );
    echo $nextRun;
    echo '</div>';
    if (current_user_can('manage_options')) {
      echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
      echo '<input type="hidden" name="action" value="cife_run_broken_link_scan">';
      wp_nonce_field('cife_run_broken_link_scan');
      submit_button(
        __('Run broken-link check now', 'content-insights-for-editors'),
        'secondary',
        'submit',
        false
      );
      echo '</form>';
    }
    if (Matomo::$matomoIsActive):
      echo $this->btnTriggerUpdateAnalytics();
    endif;
  }

  private function btnTriggerUpdateAnalytics() {
    ?>
        <div class="nestedpages-top-toggles">
            <button class="button button-primary right" id="trigger_update_analytics">
				<?php _e(
      'Fetch visitorstaticstics for all pages',
      'content-insights-for-editors'
    ); ?>
			</button>
        </div>
        <?php
  }

  public function triggerUpdateAnalytics() {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(
        new \WP_Error('forbidden', __('You are not allowed to update analytics.', 'content-insights-for-editors')),
        403
      );
    }
    check_ajax_referer('cife_update_analytics', 'nonce');

    try {
      $analytics = new Matomo();

      $analytics->updatePagesWeekAndMonth();

      wp_send_json_success();
    } catch (\Throwable $e) {
      do_action('cife_matomo_update_error', $e);
      $error = new \WP_Error(
        'matomo_update_failed',
        __('The visitor statistics could not be updated.', 'content-insights-for-editors')
      );
      wp_send_json_error($error);
    }

    wp_die();
  }

  public function enqueueScripts($hook) {
    if ($hook != 'toplevel_page_content-insights-for-editors-page') {
      return;
    }

    wp_register_script(
      'cife_js',
      plugins_url('../../js/content-insights-for-editors.js', __FILE__),
      array('jquery'),
      '0.1'
    );
    wp_localize_script('cife_js', 'WS_ANALYSIS', [
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('cife_update_analytics'),
      'strings' => [
        'update_complete' => __(
          'Update of visitor statistics complete',
          'content-insights-for-editors'
        ),
        'error' => __('error', 'content-insights-for-editors'),
        'something_went_wrong' => __(
          'Something went wrong',
          'content-insights-for-editors'
        ),
      ],
    ]);
    wp_enqueue_script('cife_js');
  }
}
