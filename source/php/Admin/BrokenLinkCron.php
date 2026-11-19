<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\MailFormatter;
class BrokenLinkCron {
  const MAIL_HOOK = 'cife_cron_mail';
  const MAIL_BATCH_HOOK = 'cife_cron_mail_batch';
  const MAIL_STATE_OPTION = 'cife_cron_mail_state';
  const MAIL_BATCH_SIZE = 20;

  function __construct() {
    add_filter(
      'acf/update_value/key=field_5d19cd83d4ea3',
      array($this, 'handleCronJobActivity'),
      10,
      3
    ); // use_broken_links_cron
    add_action('init', array($this, 'initCheckCron'));
    add_action(self::MAIL_HOOK, array($this, 'startMailRun'));
    add_action(self::MAIL_BATCH_HOOK, array($this, 'sendMailBatch'));
    add_filter('cron_schedules', array($this, 'addCustomCronTime'));
  }

  public static function deactivate() {
    wp_clear_scheduled_hook(self::MAIL_HOOK);
    wp_clear_scheduled_hook(self::MAIL_BATCH_HOOK);
    delete_option(self::MAIL_STATE_OPTION);
  }

  public function handleCronJobActivity($value, $post_id, $field) {
    if (!$value) {
      wp_clear_scheduled_hook(self::MAIL_HOOK);
      wp_clear_scheduled_hook(self::MAIL_BATCH_HOOK);
      delete_option(self::MAIL_STATE_OPTION);
    } else {
      $this->checkCron(true);
    }
    return $value;
  }

  public function initCheckCron() {
    $this->checkCron();
  }

  public function checkCron($force = false) {
    $use_broken_links_cron = get_field('use_broken_links_cron', 'options');
    if (
      !wp_next_scheduled(self::MAIL_HOOK) &&
      ($use_broken_links_cron || $force)
    ) {
      wp_schedule_event(time() + MINUTE_IN_SECONDS, 'twice_week', self::MAIL_HOOK);
    }
  }

  public function startMailRun() {
    $state = get_option(self::MAIL_STATE_OPTION);
    if (
      !is_array($state) ||
      empty($state['started_at']) ||
      (int) $state['started_at'] < time() - DAY_IN_SECONDS
    ) {
      $state = array(
        'offset' => 0,
        'started_at' => time(),
        'sent' => 0,
        'failed' => 0,
      );
      update_option(self::MAIL_STATE_OPTION, $state, false);
    }
    $this->scheduleMailBatch(1);
  }

  public function sendMailToUsers() {
    $this->startMailRun();
  }

  public function sendMailBatch() {
    $state = get_option(self::MAIL_STATE_OPTION);
    if (!is_array($state)) {
      return false;
    }

    $args = array(
      'role__in' => ['administrator', 'editor', 'author'],
      'orderby' => 'ID',
      'order' => 'ASC',
      'number' => self::MAIL_BATCH_SIZE,
      'offset' => isset($state['offset']) ? (int) $state['offset'] : 0,
      'fields' => 'ID',
    );
    $userIds = get_users($args);
    foreach ($userIds as $userId) {
      if (MailFormatter::formatAndSendMail($userId)) {
        $state['sent']++;
      } else {
        $state['failed']++;
      }
    }

    $state['offset'] += count($userIds);
    if (count($userIds) < self::MAIL_BATCH_SIZE) {
      do_action('cife_notification_mail_run_completed', $state);
      delete_option(self::MAIL_STATE_OPTION);
      return true;
    }

    update_option(self::MAIL_STATE_OPTION, $state, false);
    $this->scheduleMailBatch(MINUTE_IN_SECONDS);
    return true;
  }

  private function scheduleMailBatch($delay) {
    if (!wp_next_scheduled(self::MAIL_BATCH_HOOK)) {
      wp_schedule_single_event(time() + (int) $delay, self::MAIL_BATCH_HOOK);
    }
  }

  public function addCustomCronTime($schedules) {
    $schedules['twice_week'] = array(
      'interval' => 604800 * 2, //604800 seconds in 1 week
      'display' => esc_html__(
        'Every Second Week',
        'content-insights-for-editors'
      ),
    );
    return $schedules;
  }
}
