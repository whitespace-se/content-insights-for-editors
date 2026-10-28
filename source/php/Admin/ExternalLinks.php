<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkRepository;

class ExternalLinks {
  const PAGE_SLUG = 'content-insights-for-editors-external-links';

  public function __construct() {
    add_action('admin_menu', array($this, 'registerPage'));
    add_action('admin_post_cife_mark_external_link_checked', array($this, 'markChecked'));
  }

  public function registerPage() {
    add_submenu_page(
      Main::$MENU_SLUG,
      __('External links', 'content-insights-for-editors'),
      __('External links', 'content-insights-for-editors'),
      'edit_posts',
      self::PAGE_SLUG,
      array($this, 'render')
    );
  }

  public function render() {
    if (!current_user_can('edit_posts')) {
      wp_die(esc_html__('You are not allowed to view external links.', 'content-insights-for-editors'));
    }
    $repository = new ExternalLinkRepository();
    $userId = current_user_can('manage_options') ? false : get_current_user_id();
    $dueOnly = !empty($_GET['review_due']) && sanitize_key(wp_unslash($_GET['review_due'])) === '1';
    $page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
    $perPage = 50;
    $items = $repository->getLinks($userId, $dueOnly, $perPage, ($page - 1) * $perPage);
    $total = $repository->countLinks($userId, $dueOnly);

    echo '<div class="wrap"><h1>' . esc_html__('External links', 'content-insights-for-editors') . '</h1>';
    echo '<p>' . esc_html__('CIFE never requests external URLs. Open a link yourself, verify it, then record the review date here.', 'content-insights-for-editors') . '</p>';
    if (!empty($_GET['cife_link_checked'])) {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('The external link was marked as checked.', 'content-insights-for-editors') . '</p></div>';
    }
    $allUrl = admin_url('admin.php?page=' . self::PAGE_SLUG);
    $dueUrl = add_query_arg('review_due', '1', $allUrl);
    echo '<ul class="subsubsub"><li><a href="' . esc_url($allUrl) . '"' . (!$dueOnly ? ' class="current"' : '') . '>' . esc_html__('All', 'content-insights-for-editors') . '</a> | </li>';
    echo '<li><a href="' . esc_url($dueUrl) . '"' . ($dueOnly ? ' class="current"' : '') . '>' . esc_html__('Review due', 'content-insights-for-editors') . '</a></li></ul>';
    echo '<table class="widefat fixed striped"><thead><tr>';
    foreach (array(
      __('External link', 'content-insights-for-editors'),
      __('Appears on', 'content-insights-for-editors'),
      __('First seen', 'content-insights-for-editors'),
      __('Checked on', 'content-insights-for-editors'),
      __('Next review', 'content-insights-for-editors'),
      __('Action', 'content-insights-for-editors'),
    ) as $heading) {
      echo '<th>' . esc_html($heading) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if (!$items) {
      echo '<tr><td colspan="6">' . esc_html__('No external links found.', 'content-insights-for-editors') . '</td></tr>';
    }
    foreach ($items as $item) {
      echo '<tr><td><a target="_blank" rel="noopener noreferrer" href="' . esc_url($item->url) . '">' . esc_html($item->url) . '</a></td><td>';
      $pageLinks = array();
      foreach ($item->pages as $affectedPage) {
        $pageLinks[] = '<a href="' . esc_url(get_edit_post_link($affectedPage->post_id)) . '">' . esc_html($affectedPage->post_title) . '</a>';
      }
      echo implode('<br>', $pageLinks);
      echo '</td><td>' . esc_html(mysql2date('Y-m-d', $item->first_seen, false)) . '</td>';
      echo '<td>' . ($item->checked_at ? esc_html(mysql2date('Y-m-d', $item->checked_at, false)) : '&mdash;') . '</td>';
      echo '<td>' . esc_html($item->review_due) . '</td><td>';
      echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
      echo '<input type="hidden" name="action" value="cife_mark_external_link_checked">';
      echo '<input type="hidden" name="link_id" value="' . absint($item->id) . '">';
      echo '<input type="hidden" name="return_url" value="' . esc_attr($dueOnly ? $dueUrl : $allUrl) . '">';
      wp_nonce_field('cife_mark_external_link_' . $item->id);
      submit_button(__('Mark as checked', 'content-insights-for-editors'), 'secondary small', 'submit', false);
      echo '</form></td></tr>';
    }
    echo '</tbody></table>';
    if ($total > $perPage) {
      echo '<div class="tablenav"><div class="tablenav-pages">';
      echo wp_kses_post(paginate_links(array(
        'base' => add_query_arg('paged', '%#%', $dueOnly ? $dueUrl : $allUrl),
        'format' => '', 'current' => $page, 'total' => (int) ceil($total / $perPage),
      )));
      echo '</div></div>';
    }
    echo '</div>';
  }

  public function markChecked() {
    $linkId = isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0;
    if (!$linkId || !current_user_can('edit_posts')) {
      wp_die(esc_html__('You are not allowed to review this link.', 'content-insights-for-editors'));
    }
    check_admin_referer('cife_mark_external_link_' . $linkId);
    $repository = new ExternalLinkRepository();
    if (!$repository->userCanReview($linkId, get_current_user_id()) || !$repository->markChecked($linkId, get_current_user_id())) {
      wp_die(esc_html__('The external-link review could not be saved.', 'content-insights-for-editors'));
    }
    $fallback = admin_url('admin.php?page=' . self::PAGE_SLUG);
    $returnUrl = isset($_POST['return_url']) ? wp_unslash($_POST['return_url']) : $fallback;
    wp_safe_redirect(add_query_arg('cife_link_checked', '1', wp_validate_redirect($returnUrl, $fallback)));
    exit;
  }
}
