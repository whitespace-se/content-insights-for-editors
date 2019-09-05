<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

class ListTable extends \WP_List_Table {
	public static $useAlternateUserField = false;

	function __construct() {
		parent::__construct(array(
			'singular' => 'post',
			'plural' => 'posts',
			'ajax' => false,
		));

		self::$useAlternateUserField = \CONTENT_INSIGHTS_FOR_EDITORS\Admin\Settings::getUseAlternateUserField();
	}
	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$userID = isset($_GET['author_id'])
			? $_GET['author_id']
			: get_current_user_id();
		$showOnlyBrokenLinks =
			!empty($_GET['show_only_broken_links']) &&
			$_GET['show_only_broken_links'] == 'true';

		$data = PostQuery::getListPosts($userID, $showOnlyBrokenLinks);

		$perPage = 30;
		$currentPage = $this->get_pagenum();
		$totalItems = count($data);
		$this->set_pagination_args(array(
			'total_items' => $totalItems,
			'per_page' => $perPage,
		));
		$data = array_slice($data, ($currentPage - 1) * $perPage, $perPage);

		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $data;
	}

	protected function extra_tablenav($which) {
		if ($which == 'top') {

			$selected_user = isset($_GET['author_id'])
				? $_GET['author_id']
				: get_current_user_id();
			$show_only_broken_links = '';
			if (
				!empty($_GET['show_only_broken_links']) &&
				$_GET['show_only_broken_links'] == 'true'
			) {
				$show_only_broken_links = 'checked';
			}

			$args = array(
				'show_option_all' => __('All', 'content-insights-for-editors'),
				'role__in' => ['administrator', 'editor', 'author'],
				'orderby' => 'display_name',
				'order' => 'ASC',
				'multi' => false,
				'show' => 'display_name',
				'echo' => true,
				'selected' => $selected_user,
				'include_selected' => true,
				'name' => 'author_id',
				'blog_id' => $GLOBALS['blog_id'],
			);
			?>
            <div class="alignleft actions">
                <?php wp_dropdown_users($args); ?>
                <label style="vertical-align: -webkit-baseline-middle;">
                    <input type="checkbox" name="show_only_broken_links" value="true" <?php echo $show_only_broken_links; ?> />
                    <?php _e(
                    	'Show only pages with broken links',
                    	'content-insights-for-editors'
                    ); ?>
                </label>
            </div>
            <?php
		}
	}

	public function get_columns() {
		$basicColumns = array(
			'title' => __('Post', 'content-insights-for-editors'),
			'url' => __('Web address', 'content-insights-for-editors'),
			'length' => __('Number of broken links', 'content-insights-for-editors'),
			'modified' => __('Last updated', 'content-insights-for-editors'),
			'author' => self::$useAlternateUserField
				? __('Editor', 'content-insights-for-editors')
				: __('Author', 'content-insights-for-editors'),
		);
		$matomoColumns = Matomo::$matomoIsActive ? array(
			'week_visitors' => sprintf(
				'%s: %d %s',
				__('Visitor', 'content-insights-for-editors'),
				7,
				_n('day', 'days', 7, 'content-insights-for-editors')
			),
			'month_visitors' => sprintf(
				'%s: %d %s',
				__('Visitor', 'content-insights-for-editors'),
				30,
				_n('day', 'days', 30, 'content-insights-for-editors')
			),
			'updated_date' => __('Analysis updated', 'content-insights-for-editors'),
		) : array();
		return array_merge($basicColumns, $matomoColumns);
	}

	public function get_hidden_columns() {
		return array();
	}

	public function get_sortable_columns() {
		$basicColumns = array(
			'length' => array('length', true),
			'modified' => array('modified', false),
		);

		$matomoColumns = Matomo::$matomoIsActive ? array(
			'week_visitors' => array('week_visitors', true),
			'month_visitors' => array('month_visitors', true),
		) : array();

		return array_merge($basicColumns, $matomoColumns);
	}

	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'title':
				return '<a href="' .
					get_edit_post_link($item->ID) .
					'"><strong>' .
					$item->title .
					'</strong></a>';

			case 'url':
				return '<a target="_blank" href="' .
					get_permalink($item->ID) .
					'">' .
					get_permalink($item->ID) .
					'</a>';
			case 'author':
				return '<a href="mailto:' .
					$item->author_email .
					'">' .
					$item->author .
					'</a>';

			default:
				return $item->$column_name;
		}
	}
}
