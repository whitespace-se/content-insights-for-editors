<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class CIFE_Content_Insights_Table extends WP_List_Table {
    
    private $time_periods = ['day', 'week', 'month', 'year'];

    public function __construct() {
        parent::__construct([
            'singular' => 'content_insight',
            'plural'   => 'content_insights',
            'ajax'     => false,
            'screen'   => isset($GLOBALS['hook_suffix']) ? get_current_screen() : null,
        ]);
    }

    public function get_columns() {
        $columns = [
            'domain'          => 'Domain',
            'url_path'        => 'URL Path',
        ];

        foreach ($this->time_periods as $period) {
            $columns["{$period}_visitors"] = ucfirst($period) . ' Visitors';
            $columns["{$period}_pageviews"] = ucfirst($period) . ' Views';
        }

        $columns['updated_date'] = 'Last Updated';

        return $columns;
    }

    public function prepare_items() {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'content_insights_urls';
        $posts_table = $wpdb->prefix . 'content_insights_posts';

        $per_page = $this->get_items_per_page('cife_insights_per_page', 20);
        $current_page = $this->get_pagenum();

        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $search = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $orderby = isset($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'month_pageviews';
        $order = isset($_REQUEST['order']) ? $_REQUEST['order'] : 'DESC';

        $query = "SELECT u.*, p.post_title, cp.post_id
                  FROM $urls_table u
                  LEFT JOIN $posts_table cp ON u.id = cp.url_id
                  LEFT JOIN {$wpdb->posts} p ON cp.post_id = p.ID
                  WHERE 1=1";

        if (!empty($search)) {
            $query .= $wpdb->prepare(" AND (p.post_title LIKE %s OR u.url_path LIKE %s)", 
                                      '%' . $wpdb->esc_like($search) . '%',
                                      '%' . $wpdb->esc_like($search) . '%');
        }

        $query .= " ORDER BY u.$orderby $order";

        $total_items = $wpdb->get_var("SELECT COUNT(1) FROM ($query) AS combined_table");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->items = $wpdb->get_results($query . $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, ($current_page - 1) * $per_page), ARRAY_A);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'domain':
                return $item['domain'];
            case 'url_path':
                return $item['url_path'];
            case 'day_visitors':
            case 'day_pageviews':
            case 'week_visitors':
            case 'week_pageviews':
            case 'month_visitors':
            case 'month_pageviews':
            case 'year_visitors':
            case 'year_pageviews':
                return number_format($item[$column_name]);
            case 'updated_date':
                return date('Y-m-d', strtotime($item[$column_name]));
            default:
                return print_r($item, true);
        }
    }

    public function get_sortable_columns() {
        $sortable_columns = [
            'domain'          => ['domain', false],
            'url_path'        => ['url_path', false],
            'updated_date'    => ['updated_date', false]
        ];

        foreach ($this->time_periods as $period) {
            $sortable_columns["{$period}_visitors"] = ["{$period}_visitors", false];
            $sortable_columns["{$period}_pageviews"] = ["{$period}_pageviews", $period === 'month'];
        }

        return $sortable_columns;
    }

    public function get_hidden_columns() {
        return get_hidden_columns($this->screen);
    }
}