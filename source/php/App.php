<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS;

class App {
	public static $pluginTitle = 'Content Insights for Editors';

	public function __construct() {
		new Admin\Main();
		new Admin\Settings();
		new Admin\Metabox();
		new Admin\Dashboard();
		new Admin\BrokenLinkCron();
		new Util\BrokenLinks();
		new Util\Matomo();

		add_action('admin_enqueue_scripts', array($this, 'enqueueStyles'));
	}

	public function enqueueStyles() {
		wp_enqueue_style(
			'cife_css',
			plugins_url('../css/content-insights-for-editors.css', __FILE__)
		);
	}
}
