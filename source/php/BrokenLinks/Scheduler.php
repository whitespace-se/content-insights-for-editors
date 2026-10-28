<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

use CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings;

class Scheduler {
  const DAILY_HOOK = 'cife_broken_links_daily_scan';
  const BATCH_HOOK = 'cife_broken_links_scan_batch';
  const LEGACY_HOOK = 'broken-links-detector-external';
  const STATE_OPTION = 'cife_broken_links_scan_state';
  const LAST_COMPLETED_OPTION = 'cife_broken_links_last_completed';
  const LOCK_OPTION = 'cife_broken_links_scan_lock';
  const POST_FETCH_SIZE = 1;
  const LINK_BATCH_SIZE = 20;
  const MAX_RUNTIME = 20;

  private $contentProvider;

  public function __construct() {
    $this->contentProvider = new ContentProvider();

    add_filter('posts_where', array($this->contentProvider, 'addAfterIdClause'), 10, 2);
    add_action('init', array($this, 'ensureScheduled'));
    add_action(self::DAILY_HOOK, array($this, 'startScan'));
    add_action(self::BATCH_HOOK, array($this, 'runBatch'));
    add_action('admin_post_cife_run_broken_link_scan', array($this, 'handleManualScan'));
    add_action('before_delete_post', array($this, 'deletePostResults'));
  }

  public static function activate() {
    Installer::install();
    if (!wp_next_scheduled(self::DAILY_HOOK)) {
      wp_schedule_event(time() + MINUTE_IN_SECONDS, 'daily', self::DAILY_HOOK);
    }
  }

  public static function deactivate() {
    wp_clear_scheduled_hook(self::DAILY_HOOK);
    wp_clear_scheduled_hook(self::BATCH_HOOK);
    delete_option(self::LOCK_OPTION);
    delete_option(self::STATE_OPTION);
  }

  public function ensureScheduled() {
    if (class_exists('\\BrokenLinkDetector\\App')) {
      return;
    }

    if (wp_next_scheduled(self::LEGACY_HOOK)) {
      wp_clear_scheduled_hook(self::LEGACY_HOOK);
    }

    if (!wp_next_scheduled(self::DAILY_HOOK)) {
      wp_schedule_event(time() + MINUTE_IN_SECONDS, 'daily', self::DAILY_HOOK);
    }
  }

  public function startScan() {
    if (class_exists('\\BrokenLinkDetector\\App')) {
      return false;
    }

    $existingState = get_option(self::STATE_OPTION);
    if (
      is_array($existingState) &&
      !empty($existingState['started_at']) &&
      (int) $existingState['started_at'] > time() - DAY_IN_SECONDS
    ) {
      if (!wp_next_scheduled(self::BATCH_HOOK)) {
        wp_schedule_single_event(time() + 1, self::BATCH_HOOK);
      }
      return true;
    }

    update_option(
      self::STATE_OPTION,
      array(
        'last_id' => 0,
        'started_at' => time(),
        'checked' => 0,
      ),
      false
    );

    if (!wp_next_scheduled(self::BATCH_HOOK)) {
      wp_schedule_single_event(time() + 1, self::BATCH_HOOK);
    }

    return true;
  }

  public function runBatch() {
    $lockToken = $this->acquireLock();
    if (!$lockToken) {
      return false;
    }

    try {
      $state = get_option(self::STATE_OPTION);
      if (!is_array($state)) {
        return false;
      }

      $scanner = new Scanner(null, $this->contentProvider);
      $runtime = (int) apply_filters(
        'cife_broken_links_batch_runtime',
        self::MAX_RUNTIME
      );
      $deadline = microtime(true) + max(1, $runtime);
      $completeScan = false;

      while (microtime(true) < $deadline) {
        if (empty($state['current_post_id'])) {
          $postIds = $this->contentProvider->getEligiblePostIds(
            isset($state['last_id']) ? (int) $state['last_id'] : 0,
            self::POST_FETCH_SIZE
          );
          if (!$postIds) {
            $completeScan = true;
            break;
          }
          $state['current_post_id'] = (int) $postIds[0];
          $state['url_offset'] = 0;
          $state['broken_urls'] = array();
        }

        $result = $scanner->scanPostBatch(
          (int) $state['current_post_id'],
          isset($state['url_offset']) ? (int) $state['url_offset'] : 0,
          isset($state['broken_urls']) && is_array($state['broken_urls'])
            ? $state['broken_urls']
            : array(),
          self::LINK_BATCH_SIZE,
          $deadline
        );
        $state['url_offset'] = $result['next_offset'];
        $state['broken_urls'] = $result['broken_urls'];

        if (!$result['complete']) {
          break;
        }
        if (!$result['saved']) {
          throw new \RuntimeException('Could not save broken-link scan results.');
        }

        $state['last_id'] = (int) $state['current_post_id'];
        $state['checked'] = isset($state['checked']) ? $state['checked'] + 1 : 1;
        unset($state['current_post_id'], $state['url_offset'], $state['broken_urls']);
      }

      if ($completeScan) {
        $repository = new Repository();
        $repository->deleteIneligible(
          Settings::getAnalysedPostTypes(),
          array('publish')
        );
        update_option(self::LAST_COMPLETED_OPTION, time(), false);
        delete_option(self::STATE_OPTION);
      } else {
        update_option(self::STATE_OPTION, $state, false);
        $this->scheduleContinuation(MINUTE_IN_SECONDS);
      }
    } catch (\Throwable $error) {
      $this->scheduleContinuation(5 * MINUTE_IN_SECONDS);
      do_action('cife_broken_links_scan_error', $error);
      return false;
    } finally {
      $this->releaseLock($lockToken);
    }

    return true;
  }

  public function handleManualScan() {
    if (!current_user_can('manage_options')) {
      wp_die(__('You are not allowed to run this check.', 'content-insights-for-editors'));
    }

    check_admin_referer('cife_run_broken_link_scan');
    $started = $this->startScan();

    wp_safe_redirect(
      add_query_arg(
        $started ? 'cife_scan_started' : 'cife_scan_error',
        $started ? '1' : 'legacy_plugin',
        admin_url('admin.php?page=content-insights-for-editors-page')
      )
    );
    exit;
  }

  public function deletePostResults($postId) {
    $repository = new Repository();
    $repository->deleteForPost($postId);
  }

  public static function nextScheduledTimestamp() {
    $hook = class_exists('\\BrokenLinkDetector\\App')
      ? self::LEGACY_HOOK
      : self::DAILY_HOOK;
    return wp_next_scheduled($hook);
  }

  private function scheduleContinuation($delay) {
    if (!wp_next_scheduled(self::BATCH_HOOK)) {
      wp_schedule_single_event(time() + (int) $delay, self::BATCH_HOOK);
    }
  }

  private function acquireLock() {
    $token = function_exists('wp_generate_uuid4')
      ? wp_generate_uuid4()
      : uniqid('cife-', true);
    $lock = array('token' => $token, 'expires' => time() + 5 * MINUTE_IN_SECONDS);
    if (add_option(self::LOCK_OPTION, $lock, '', false)) {
      return $token;
    }

    $existing = get_option(self::LOCK_OPTION);
    if (is_array($existing) && isset($existing['expires']) && (int) $existing['expires'] < time()) {
      delete_option(self::LOCK_OPTION);
      if (add_option(self::LOCK_OPTION, $lock, '', false)) {
        return $token;
      }
    }
    return false;
  }

  private function releaseLock($token) {
    $lock = get_option(self::LOCK_OPTION);
    if (is_array($lock) && isset($lock['token']) && hash_equals($lock['token'], $token)) {
      delete_option(self::LOCK_OPTION);
    }
  }
}
