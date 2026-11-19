<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks;

class ExternalLinkCheckScheduler {
  const DAILY_HOOK = 'cife_external_links_daily_check';
  const WORKER_HOOK = 'cife_external_links_check_one';
  const LOCK_OPTION = 'cife_external_links_check_lock';

  private $config;

  public function __construct(ExternalLinkCheckConfig $config = null) {
    $this->config = $config;
    add_action('init', array($this, 'ensureScheduled'));
    add_action(self::DAILY_HOOK, array($this, 'start'));
    add_action(self::WORKER_HOOK, array($this, 'runOne'));
  }

  public static function activate() {
    $config = new ExternalLinkCheckConfig();
    if ($config->isEnabled() && !wp_next_scheduled(self::DAILY_HOOK)) {
      wp_schedule_event(time() + MINUTE_IN_SECONDS, 'daily', self::DAILY_HOOK);
    }
  }

  public static function deactivate() {
    wp_clear_scheduled_hook(self::DAILY_HOOK);
    wp_clear_scheduled_hook(self::WORKER_HOOK);
    delete_option(self::LOCK_OPTION);
  }

  public function ensureScheduled() {
    $config = $this->getConfig();
    if (!$config->isEnabled()) {
      if (wp_next_scheduled(self::DAILY_HOOK)) {
        wp_clear_scheduled_hook(self::DAILY_HOOK);
      }
      if (wp_next_scheduled(self::WORKER_HOOK)) {
        wp_clear_scheduled_hook(self::WORKER_HOOK);
      }
      if (get_option(self::LOCK_OPTION, false) !== false) {
        delete_option(self::LOCK_OPTION);
      }
      return;
    }
    if (!wp_next_scheduled(self::DAILY_HOOK)) {
      wp_schedule_event(time() + MINUTE_IN_SECONDS, 'daily', self::DAILY_HOOK);
    }
  }

  public function start() {
    if (!$this->getConfig()->isEnabled()) {
      return false;
    }
    $this->scheduleWorker(1);
    return true;
  }

  public function runOne() {
    $config = $this->getConfig();
    if (!$config->isEnabled()) {
      return false;
    }
    $lockToken = $this->acquireLock();
    if (!$lockToken) {
      return false;
    }

    try {
      $repository = new ExternalLinkRepository();
      $link = $repository->getNextAutomaticCheck(
        $config->getHosts(),
        $config->getIntervalDays()
      );
      if (!$link) {
        return true;
      }
      $result = (new ExternalLinkChecker($config))->check($link->url);
      if (!$repository->saveAutomaticResult($link->id, $result)) {
        throw new \RuntimeException('Could not save automatic external-link result.');
      }
      $this->scheduleWorker($config->getRequestDelay());
    } catch (\Throwable $error) {
      do_action('cife_external_link_check_error', $error);
      $this->scheduleWorker(5 * MINUTE_IN_SECONDS);
      return false;
    } finally {
      $this->releaseLock($lockToken);
    }
    return true;
  }

  private function getConfig() {
    return $this->config ?: new ExternalLinkCheckConfig();
  }

  private function scheduleWorker($delay) {
    if (!wp_next_scheduled(self::WORKER_HOOK)) {
      wp_schedule_single_event(time() + max(1, (int) $delay), self::WORKER_HOOK);
    }
  }

  private function acquireLock() {
    $token = function_exists('wp_generate_uuid4')
      ? wp_generate_uuid4()
      : uniqid('cife-external-', true);
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
