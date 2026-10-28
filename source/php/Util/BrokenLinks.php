<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Installer;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Repository;
use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\Scheduler;

class BrokenLinks {
  public static $dbTable;

  public static $nextScheduledRun = null;
  private $scheduler;

  public function __construct() {
    self::$dbTable = Repository::tableName();
    $this->scheduler = new Scheduler();

    add_action('plugins_loaded', array($this, 'initialize'), 20);
    add_action('init', array($this, 'refreshNextScheduledRun'), 20);
  }

  public static function activate() {
    Installer::install();
    Scheduler::activate();
  }

  public static function deactivate() {
    Scheduler::deactivate();
  }

  public function initialize() {
    Installer::maybeInstall();
  }

  public function refreshNextScheduledRun() {
    $timestamp = Scheduler::nextScheduledTimestamp();
    self::$nextScheduledRun = $timestamp
      ? wp_date('Y-m-d H:i', $timestamp, wp_timezone())
      : null;
  }
}
