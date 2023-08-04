<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS;

class ListTable extends \WP_List_Table
{
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = self::getBrokenLinks();

        $perPage = 30;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);
        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ));
        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    public static function getBrokenLinks($postId = false)
    {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $postId = isset($_POST['post_id']) ? $_POST['post_id'] : false;
        }

        App::checkInstall();

        global $wpdb;
        $tableName = App::$bldTable;

        $sql = "SELECT
                    links.*,
                    {$wpdb->posts}.*,
                    {$wpdb->posts}.ID AS post_id
                FROM $tableName links
                LEFT JOIN $wpdb->posts ON {$wpdb->posts}.ID = links.post_id";

        if (is_numeric($postId)) {
            $sql .= " WHERE {$wpdb->posts}.ID = $postId";
        }

        $sql .= " ORDER BY {$wpdb->posts}.post_title";

        $result = $wpdb->get_results($sql);

        if (defined('DOING_AJAX') && DOING_AJAX) {
            echo json_encode($result);
            wp_die();
        }

        return $result;
    }

    public static function getBrokenLinksCount($postId = false)
    {
        global $wpdb;
        $tableName = App::$bldTable;
        $sql = "SELECT
                    COUNT(*) AS length
                FROM $tableName links
                LEFT JOIN $wpdb->posts ON {$wpdb->posts}.ID = links.post_id";

        if (is_numeric($postId)) {
            $sql .= " WHERE {$wpdb->posts}.ID = $postId";
        }

        $sql .= " ORDER BY {$wpdb->posts}.post_title";

        $result = $wpdb->get_var($sql);

        return $result;
    }

    public function get_columns()
    {
        return array(
            'post' => __('Post', 'broken-link-detector'),
            'url' => __('Web adress', 'broken-link-detector'),
            'time' => __('Last probed', 'broken-link-detector')
        );
    }

    public function get_hidden_columns()
    {
        return array();
    }

    public function get_sortable_columns()
    {
        return array(
            'post' => array('post', false),
            'url' => array('url', false),
            'time' => array('url', false),
        );
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'post':
                return '<a href="' . get_edit_post_link($item->post_id) . '"><strong>' . $item->post_title . '</strong></a>';

            case 'url':
                return '<a target="_blank" href="' . $item->url . '">' . $item->url . '</a>';

            default:
                return $item->$column_name;
        }
    }
}
