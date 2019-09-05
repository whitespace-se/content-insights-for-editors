<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

//Wrapper for broken-links-detector
class BrokenLinks {
	public static $dbTable;

	public static $nextScheduledRun = null;

	public function __construct() {
		if (!class_exists('\BrokenLinkDetector\App')) {
			add_action( 'admin_notices', array($this, 'adminNoticeWarnMissingPlugin') );
			return;
		}
		\BrokenLinkDetector\App::checkInstall();

		self::$dbTable = \BrokenLinkDetector\App::$dbTable;

		// Copy from /broken-link-detector/source/php/App.php
		$offset = get_option('gmt_offset');
		if ($offset > -1) {
			$offset = '+' . $offset;
		} else {
			$offset = '-' . 1 * abs($offset);
		}

		self::$nextScheduledRun = date(
			'Y-m-d H:i',
			strtotime(
				$offset . ' hours',
				wp_next_scheduled('broken-links-detector-external')
			)
		);
	}

	public function adminNoticeWarnMissingPlugin() {
		?>
		<div class="notice notice-error">
			<p><?php _e( 'Broken links plugin is missing. For best experience using this plugin we recommend you activate Broken links', 'content-insights-for-editors' ); ?></p>
			<a href="https://github.com/helsingborg-stad/broken-link-detector"><?php _e('Broken links repository', 'content-insights-for-editors'); ?></a>
		</div>
		<?php
	}
}
