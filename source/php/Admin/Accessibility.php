<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;
use CONTENT_INSIGHTS_FOR_EDITORS\Util\Crawler;

class Accessibility {
	public static $MENU_SLUG = 'content-insights-for-editors-page';

	public function __construct() {
		add_action('admin_menu', array($this, 'init'));
	}

	public function init() {
		add_submenu_page(
			self::$MENU_SLUG,
			__('Accessibility', 'content-insights-for-editors'),
			__('Accessibility', 'content-insights-for-editors'),
			'manage_options',
			self::$MENU_SLUG . '-accessibility',
			array($this, 'render')
		);
    }
    
    public function render() {        
        echo '<div>';
        echo "Work in progress";
		echo '</div>';
	}
}