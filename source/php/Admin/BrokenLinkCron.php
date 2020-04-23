<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\MailFormatter;
class BrokenLinkCron {
  function __construct() {
    add_filter(
      'acf/update_value/key=field_5d19cd83d4ea3',
      array($this, 'handleCronJobActivity'),
      10,
      3
    ); // use_broken_links_cron
    add_action('wp', array($this, 'initCheckCron'));
    add_action('cife_cron_mail', array($this, 'sendMailToUsers'));
    add_filter('cron_schedules', array($this, 'addCustomCronTime'));
  }

  public function handleCronJobActivity($value, $post_id, $field) {
    if (!$value) {
      wp_clear_scheduled_hook('cife_cron_mail');
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
      !wp_next_scheduled('cife_cron_mail') &&
      ($use_broken_links_cron || $force)
    ) {
      wp_schedule_event(time(), 'twice_week', 'cife_cron_mail');
    }
  }

  public function sendMailToUsers() {
    $args = array(
      'role__in' => ['administrator', 'editor', 'author'],
      'orderby' => 'login',
    );
    $users = get_users($args);
    foreach ($users as $user) {
      MailFormatter::formatAndSendMail($user->ID);
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
