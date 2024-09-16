<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CIFE_Matomo {
    private $db_table_urls;
    private $db_table_posts;
    private $matomo_url;
    private $site_id;
    private $token_auth;

    public function __construct() {
        global $wpdb;
        $this->db_table_urls = $wpdb->prefix . 'content_insights_urls';
        $this->db_table_posts = $wpdb->prefix . 'content_insights_posts';
        $this->matomo_url = get_option('matomo_api_url');
        $this->site_id = get_option('matomo_id_site');
        $this->token_auth = get_option('matomo_api_key');

        $this->create_tables(); // Ensure tables are created
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
    
        $sql_urls = "CREATE TABLE IF NOT EXISTS {$this->db_table_urls} (
            id INT NOT NULL AUTO_INCREMENT,
            domain VARCHAR(255) NOT NULL,
            url_path varchar(1023) DEFAULT '' NOT NULL,
            day_visitors INT DEFAULT 0,
            day_pageviews INT DEFAULT 0,
            week_visitors INT DEFAULT 0,
            week_pageviews INT DEFAULT 0,
            month_visitors INT DEFAULT 0,
            month_pageviews INT DEFAULT 0,
            year_visitors INT DEFAULT 0,
            year_pageviews INT DEFAULT 0,
            updated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY (domain, url_path)
        ) $charset_collate;";
    
        $sql_posts = "CREATE TABLE IF NOT EXISTS {$this->db_table_posts} (
            id INT NOT NULL AUTO_INCREMENT,
            post_id INT DEFAULT NULL,
            url_id INT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY (post_id),
            FOREIGN KEY (url_id) REFERENCES {$this->db_table_urls}(id) ON DELETE CASCADE
        ) $charset_collate;";
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_urls);
        dbDelta($sql_posts);
    }

    public function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$this->db_table_posts}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->db_table_urls}");
    }

    public function empty_matomo_data() {
        global $wpdb;
        
        // Start a transaction
        $wpdb->query('START TRANSACTION');
    
        try {
            // First, delete all rows from the posts table
            $result_posts = $wpdb->query("DELETE FROM {$this->db_table_posts}");
            
            if ($result_posts === false) {
                throw new Exception($wpdb->last_error);
            }
    
            // Then, delete all rows from the urls table
            $result_urls = $wpdb->query("DELETE FROM {$this->db_table_urls}");
            
            if ($result_urls === false) {
                throw new Exception($wpdb->last_error);
            }
    
            // If we've made it this far, commit the transaction
            $wpdb->query('COMMIT');
            
            return true;
        } catch (Exception $e) {
            // If an error occurred, rollback the transaction
            $wpdb->query('ROLLBACK');
            
            error_log('Failed to empty Matomo data tables: ' . $e->getMessage());
            return new WP_Error('db_error', __('Failed to empty Matomo data tables: ', 'content-insights-for-editors') . $e->getMessage());
        }
    }

    public function is_configured() {
        $is_configured = !empty($this->matomo_url) && !empty($this->site_id) && !empty($this->token_auth);
        if (!$is_configured) {
            return new WP_Error('matomo_not_configured', __('Matomo is not configured. Please enter your Matomo API URL, Site ID, and API Key in the settings.', 'content-insights-for-editors'));
        }
        return true;
    }

    public function test_connection() {
        $is_configured = $this->is_configured();
        if (is_wp_error($is_configured)) {
            return $is_configured;
        }

        $params = array(
            'module' => 'API',
            'method' => 'API.getMatomoVersion',
            'format' => 'JSON',
            'token_auth' => $this->token_auth,
        );

        $url = add_query_arg($params, $this->matomo_url);
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return new WP_Error('matomo_connection_failed', __('Failed to connect to Matomo. Error: ', 'content-insights-for-editors') . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data)) {
            return new WP_Error('matomo_invalid_response', __('Received an invalid response from Matomo. Please check your API URL and credentials.', 'content-insights-for-editors'));
        }

        return true;
    }

    public function fetch_and_save_matomo_data() {
        $this->create_tables(); // Ensure tables exist before saving data
    
        $connection_test = $this->test_connection();
        if (is_wp_error($connection_test)) {
            return $connection_test;
        }
    
        $data = $this->fetch_matomo_data();
        if (is_wp_error($data)) {
            return $data;
        }
    
        // Merge the data from different periods
        $merged_data = $this->merge_matomo_data($data);
    
        // Log some information about the merged data
        $fetched_count = count($merged_data);
        error_log("Total unique URLs after merging: " . $fetched_count);
    
        $save_result = $this->save_matomo_data($merged_data);
        if (is_wp_error($save_result)) {
            return $save_result;
        }
    
        // Get the count of successfully imported URLs
        $imported_count = $save_result['success_count'];
    
        // Create a detailed success message
        $message = sprintf(
            __('Matomo data fetched and saved successfully. Fetched %d unique URLs.', 'content-insights-for-editors'),
            $fetched_count,
        );
    
        return $message;
    }

    private function fetch_matomo_data() {
        $periods = ['day', 'week', 'month', 'year'];
        $all_data = [];
    
        foreach ($periods as $period) {
            $page = 1;
            $per_page = 100; // Adjust this value as needed
            $all_data[$period] = []; // Initialize the array for this period
    
            $date = $this->get_date_range($period);
    
            do {
                $params = array(
                    'module' => 'API',
                    'method' => 'Actions.getPageUrls',
                    'idSite' => $this->site_id,
                    'period' => $period,
                    'date' => $date,
                    'format' => 'JSON',
                    'filter_limit' => $per_page,
                    'filter_offset' => ($page - 1) * $per_page,
                    'token_auth' => $this->token_auth,
                    'expanded' => 1,
                    'flat' => 1,
                );
    
                $url = add_query_arg($params, $this->matomo_url);    
                $response = wp_remote_get($url);
    
                if (is_wp_error($response)) {
                    error_log("Error fetching data for $period: " . $response->get_error_message());
                    return new WP_Error('matomo_fetch_failed', __('Error fetching Matomo data: ', 'content-insights-for-editors') . $response->get_error_message());
                }
    
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
    
                if (empty($data)) {
                    error_log("No data returned for $period (date: $date)");
                    break; // No more data to fetch
                }
    
                $all_data[$period] = array_merge($all_data[$period], $data);
                $page++;
    
                error_log("Fetched " . count($data) . " items for $period (date: $date). Total: " . count($all_data[$period]));
    
            } while (count($data) == $per_page); // Continue if we got a full page of results
    
            error_log("Final number of items for $period (date: $date): " . count($all_data[$period]));
        }
    
        if (empty($all_data)) {
            return new WP_Error('matomo_empty_data', __('No data returned from Matomo API.', 'content-insights-for-editors'));
        }
    
        return $all_data;
    }

    private function get_date_range($period) {
        $today = new DateTime('today', new DateTimeZone('UTC'));
        
        switch ($period) {
            case 'day':
                return $today->modify('-1 day')->format('Y-m-d');
            case 'week':
                return $today->modify('last sunday')->format('Y-m-d');
            case 'month':
                return $today->modify('last day of last month')->format('Y-m-d');
            case 'year':
                return $today->modify('last day of december last year')->format('Y-m-d');
            default:
                return $today->format('Y-m-d');
        }
    }

    private function merge_matomo_data($data) {
        $merged_data = array();
        $filter_query_vars = get_option('cife_filter_query_vars', '0') === '1';
    
        foreach ($data as $period => $period_data) {
            foreach ($period_data as $item) {
                if (empty($item['url'])) {
                    continue;
                }
    
                $parsed_url = parse_url($item['url']);
                $domain = $parsed_url['host'];
                $path = $parsed_url['path'] ?? '/';
    
                // Create a key for merging, either with or without query string
                if ($filter_query_vars) {
                    $key = $domain . $path;
                } else {
                    $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
                    $key = $domain . $path . $query;
                }
    
                if (!isset($merged_data[$key])) {
                    $merged_data[$key] = array(
                        'domain' => $domain,
                        'url_path' => $path . ($filter_query_vars ? '' : $query),
                        'day_visitors' => 0,
                        'day_pageviews' => 0,
                        'week_visitors' => 0,
                        'week_pageviews' => 0,
                        'month_visitors' => 0,
                        'month_pageviews' => 0,
                        'year_visitors' => 0,
                        'year_pageviews' => 0
                    );
                }
    
                // Accumulate the data
                $merged_data[$key]["{$period}_visitors"] += isset($item['nb_visits']) ? $item['nb_visits'] : 0;
                $merged_data[$key]["{$period}_pageviews"] += isset($item['nb_hits']) ? $item['nb_hits'] : 0;
            }
        }
    
        return $merged_data;
    }

    private function save_matomo_data($merged_data) {
        global $wpdb;
    
        $success_count = 0;
        $update_count = 0;
        $insert_count = 0;
        $error_count = 0;
        $error_messages = array();
    
        // Start a transaction
        $wpdb->query('START TRANSACTION');
    
        try {
            foreach ($merged_data as $full_url => $data) {
                // Check URL length
                if (strlen($data['url_path']) > 1023) {
                    $error_count++;
                    $error_messages[] = "URL path too long (max 1023 characters): " . substr($data['url_path'], 0, 50) . "...";
                    continue;
                }
    
                // Check if the URL already exists
                $existing_url = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->db_table_urls} WHERE domain = %s AND url_path = %s",
                    $data['domain'], $data['url_path']
                ));
    
                if ($existing_url) {
                    // Update existing record
                    $result_url = $wpdb->update(
                        $this->db_table_urls,
                        $data,
                        array('id' => $existing_url),
                        array('%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s'),
                        array('%d')
                    );
                    if ($result_url !== false) $update_count++;
                } else {
                    // Insert new record
                    $result_url = $wpdb->insert(
                        $this->db_table_urls,
                        $data,
                        array('%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s')
                    );
                    if ($result_url !== false) $insert_count++;
                }
    
                if ($result_url !== false) {
                    $url_id = $existing_url ? $existing_url : $wpdb->insert_id;
                    $post_id = url_to_postid($full_url);
    
                    if ($post_id) {
                        // Insert or update post-URL relationship
                        $result_post = $wpdb->replace(
                            $this->db_table_posts,
                            array(
                                'post_id' => $post_id,
                                'url_id' => $url_id
                            ),
                            array('%d', '%d')
                        );
    
                        if ($result_post !== false) {
                            $success_count++;
                        } else {
                            $error_count++;
                            $error_messages[] = "Error updating post-URL relationship: " . $wpdb->last_error;
                        }
                    } else {
                        $success_count++; // Count as success even if no post_id found
                    }
                } else {
                    $error_count++;
                    $error_messages[] = "Error inserting/updating URL: " . $wpdb->last_error;
                }
            }
    
            // If we've made it this far, commit the transaction
            $wpdb->query('COMMIT');
    
            $log_message = sprintf(
                "Matomo data processing complete. Total: %d, Successful: %d, Updates: %d, Inserts: %d, Errors: %d",
                count($merged_data),
                $success_count,
                $update_count,
                $insert_count,
                $error_count
            );
            error_log($log_message);
    
            if (!empty($error_messages)) {
                error_log("Errors encountered during Matomo data save: " . implode(", ", $error_messages));
            }
    
            if ($success_count === 0) {
                return new WP_Error('matomo_save_failed', __('Failed to save any Matomo data.', 'content-insights-for-editors'));
            }
    
            return array(
                'success' => true,
                'success_count' => $success_count,
                'update_count' => $update_count,
                'insert_count' => $insert_count,
                'error_count' => $error_count
            );
        } catch (Exception $e) {
            // If an error occurred, rollback the transaction
            $wpdb->query('ROLLBACK');
            
            $error_message = __('Error saving Matomo data: ', 'content-insights-for-editors') . $e->getMessage();
            error_log($error_message);
            return new WP_Error('matomo_save_failed', $error_message);
        }
    }
}