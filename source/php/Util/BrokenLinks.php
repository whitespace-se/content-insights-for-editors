<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

//Wrapper for broken-links-detector
class BrokenLinks {
	public static $dbTable;

	public static $nextScheduledRun;

	public function __construct() {
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
}
