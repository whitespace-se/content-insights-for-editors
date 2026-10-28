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
    add_action('admin_notices', array($this, 'adminNoticeLegacyPlugin'));
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

  public function adminNoticeLegacyPlugin() {
    if (!class_exists('\BrokenLinkDetector\App')) {
      return;
    }
    ?>
		<div class="notice notice-warning">
			<p><?php _e(
		'The legacy Broken Link Detector plugin is active. CIFE will keep using its schedule until the legacy plugin is removed.',
		'content-insights-for-editors'
	); ?></p>
		</div>
		<?php
  }
}
