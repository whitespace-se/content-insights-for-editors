<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class CIFE_Content_Insights_Table extends WP_List_Table {
    
    public function __construct() {
        parent::__construct([
            'singular' => 'content_insight',
            'plural'   => 'content_insights',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'              => '<input type="checkbox" />',
            // 'post_title'      => 'Post Title',
            'domain'          => 'Domain',
            'url_path'        => 'URL Path',
            'day_visitors'    => 'Daily',
            'day_pageviews'   => 'Views',
            'week_visitors'   => 'Weekly',
            'week_pageviews'  => 'Views',
            'month_visitors'  => 'Monthly',
            'month_pageviews' => 'Views',
            'year_visitors'   => 'Yearly',
            'year_pageviews'  => 'Views',
            'updated_date'    => 'Last Updated'
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $urls_table = $wpdb->prefix . 'content_insights_urls';
        $posts_table = $wpdb->prefix . 'content_insights_posts';

        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
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

        $paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
        $total_pages = ceil($total_items / $per_page);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => $total_pages
        ]);

        $this->items = $wpdb->get_results($query . $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $paged * $per_page), ARRAY_A);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'post_title':
                return $item['post_title'] ? $item['post_title'] : 'N/A';
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
                return date('Y-m-d H:i:s', strtotime($item[$column_name]));
            default:
                return print_r($item, true);
        }
    }

    public function get_sortable_columns() {
        return [
            // 'post_title'      => ['post_title', false],
            'domain'          => ['domain', false],
            'url_path'        => ['url_path', false],
            'day_visitors'    => ['day_visitors', false],
            'day_pageviews'   => ['day_pageviews', false],
            'week_visitors'   => ['week_visitors', false],
            'week_pageviews'  => ['week_pageviews', false],
            'month_visitors'  => ['month_visitors', false],
            'month_pageviews' => ['month_pageviews', true],  // Set as the default sort column
            'year_visitors'   => ['year_visitors', false],
            'year_pageviews'  => ['year_pageviews', false],
            'updated_date'    => ['updated_date', false]
        ];
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="content_insight[]" value="%s" />', $item['id']);
    }
}