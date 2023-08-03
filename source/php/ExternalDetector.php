<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS;

class ExternalDetector
{
    public function __construct()
    {
        add_action('wp', array($this, 'schedule'));
        add_action('broken-links-detector-external', array($this, 'lookForBrokenLinks'));

        add_action('save_post', function ($postId) {
            if (wp_is_post_revision($postId) || !isset($_POST['broken-link-detector-rescan']) || $_POST['broken-link-detector-rescan'] !== 'true') {
                return;
            }

            $this->lookForBrokenLinks($postId);
        });

        if (isset($_GET['broken-links-detector']) && $_GET['broken-links-detector'] == 'scan') {
            do_action('broken-links-detector-external');
        }
    }

    public function schedule()
    {
        if (wp_next_scheduled('broken-links-detector-external')) {
            return;
        }

        wp_schedule_event(time(), 'daily', 'broken-links-detector-external');
    }

    /**
     * Look for broken links in post_content
     *
     * @param  integer $post_id Optional post_id to update broken links for
     * @return void
     */
    public function lookForBrokenLinks($postId = null, $url = null)
    {
        App::checkInstall();
        $foundUrls = array();

        if ($url) {
            $url = "REGEXP ('.*(href=\"{$url}\").*')";
        } else {
            $url = "RLIKE ('href=*')";
        }

        global $wpdb;
        $sql = "
            SELECT ID, post_content
            FROM $wpdb->posts
            WHERE
                post_content {$url}
                AND post_type NOT IN ('attachment', 'revision', 'acf', 'acf-field', 'acf-field-group')
                AND post_status IN ('publish', 'private', 'password')
        ";

        if (is_numeric($postId)) {
            $sql .= " AND ID = $postId";
        }

        $posts = $wpdb->get_results($sql);

        if(is_array($posts) && !empty($posts)) {
            foreach ($posts as $post) {
                preg_match_all('/<a[^>]+href=([\'"])(http|https)(.+?)\1[^>]*>/i', $post->post_content, $m);

                if (!isset($m[3]) || count($m[3]) > 0) {
                    foreach ($m[3] as $key => $url) {
                        $url = $m[2][$key] . $url;

                        // Replace whitespaces in url
                        if (preg_match('/\s/', $url)) {
                            $newUrl = preg_replace('/ /', '%20', $url);
                            $wpdb->query(
                                $wpdb->prepare(
                                    "UPDATE $wpdb->posts
                                        SET post_content = REPLACE(post_content, %s, %s)
                                        WHERE post_content LIKE %s
                                        AND ID = %d",
                                    $url,
                                    $newUrl,
                                    '%' . $wpdb->esc_like($url) . '%',
                                    $post->ID
                                )
                            );
                            $url = $newUrl;
                        }

                        if ($postId !== 'internal' && !$this->isBroken($url)) {
                            continue;
                        }

                        $foundUrls[] = array(
                            'post_id' => $post->ID,
                            'url' => $url
                        );
                    }
                }
            }
        }
        
        /* START MODULE SUPPORT */
        if (is_numeric($postId)) {
            $modules = array();
            $sql = "select meta_value from $wpdb->postmeta where meta_key='modularity-modules' and post_id = $postId";
            $modularity = $wpdb->get_results($sql);
            foreach($modularity as $module) {
                $data = unserialize($module->meta_value);
                $keys = array_keys($data);
                foreach($keys as $k) {
                    $modules[] = array_column($data[$k], 'postid');
                }
            }
            $modules = array_merge(...array_values($modules));
            foreach($modules as $mid) {
                $meta = get_post_meta($mid, $key = '', $single = false );
                if(isset($meta['data']) && $meta['data'][0] > 0) {
                    for($i = 0; $i < $meta['data'][0]; $i++) {
                        $metakey = "data_".$i."_post_content";
                        preg_match_all('/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $meta[$metakey][0], $matches);
                        foreach($matches[0] as $url) {
                            if (!$this->isBroken($url)) {
                                continue;
                            }
                            $foundUrls[] = array(
                                'post_id' => $postId,
                                'url' => $url
                            );
                        }
                    }
                }
                $mpost = get_post($mid);
                preg_match_all('/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $mpost->post_content, $matches);
                foreach($matches[0] as $url) {
                    if (!$this->isBroken($url)) {
                        continue;
                    }
                    $foundUrls[] = array(
                        'post_id' => $postId,
                        'url' => $url
                    );
                }
            }
        }
        /* END MODULE SUPPORT */

        $this->saveBrokenLinks($foundUrls, $postId);
    }

    /**
     * Save broken links (if not already in db)
     * @param  array $data
     * @return void
     */
    public function saveBrokenLinks($data, $postId = null)
    {
        global $wpdb;

        $inserted = array();
        $tableName = App::$dbTable;

        if (is_numeric($postId)) {
            $wpdb->delete($tableName, array('post_id' => $postId), array('%d'));
        } elseif (is_null($postId)) {
            $wpdb->query("TRUNCATE $tableName");
        }

        if(!empty($data) && is_array($data)) {
            foreach ($data as $item) {
                $exists = $wpdb->get_row("SELECT id FROM $tableName WHERE post_id = {$item['post_id']} AND url = '{$item['url']}'");
                
                if ($exists) {
                    continue;
                }
                
                $inserted[] = $wpdb->insert($tableName, array(
                    'post_id' => $item['post_id'],
                    'url' => $item['url']
                ), array('%d', '%s'));
            }
        }
        
        return true;
    }

    /**
     * Test if domain is valid with different methods
     * @param  string $url Url to check
     * @return boolean
     */
    public function isBroken($url)
    {
        $domain = parse_url($url, PHP_URL_HOST);
        /*
        if (!$domain = parse_url($url, PHP_URL_HOST)) {
            return true;
        }
        */

        if(in_array($domain, (array) apply_filters('brokenLinks/External/ExceptedDomains', array()))) {
            return false;
        }

        // Convert domain name to IDNA ASCII form
        if(count(explode('.', $domain)) == count(array_filter(explode('.', $domain),
            function($var) {
                if(strlen($var) < 1) {
                    return false; 
                }
                return true; 
            })))  
        {
            try {
                $domainAscii = idn_to_ascii($domain);
                $url = str_ireplace($domain, $domainAscii, $url);
            } catch (Exception $e) {
                return false;
            }
        }

        // Test if URL is internal and page exist
        if ($this->isInternal($url)) {
            return false;
        }

        // Validate domain name
        if (!$this->isValidDomainName(isset($domainAscii) ? $domainAscii : $domain)) {
            return true;
        }

        // Test if domain is available
        return !$this->isDomainAvailable($url);
    }

    /**
     * Test if domain name is valid
     * @param string $domainName Url to check
     * @return bool
     */
    public function isValidDomainName($domainName)
    {
        return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domainName)
            && preg_match("/^.{1,253}$/", $domainName)
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domainName));
    }

    /**
     * Test if domain is available with curl
     * @param string $url Url to check
     * @return bool
     */
    public function isDomainAvailable($url, $timeOut = 7)
    {
        // Init curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeOut);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
        // Get the response
        $response    = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        $curlErrorNo = curl_errno($ch); 
        curl_close($ch);

        //Curl response error
        if($curlErrorNo) {
            if(in_array($curlErrorNo, array(CURL_TOO_MANY_REDIRECTS))) {
                if(defined("BROKEN_LINKS_LOG") && BROKEN_LINKS_LOG) {
                    error_log("Broken links: Could not probe url " . $url . " due to a malfunction of curl [" . $curlErrorNo. " - " . $curlError . "]"); 
                }
                return true; //Do not log
            } else {
                if(defined("BROKEN_LINKS_LOG") && BROKEN_LINKS_LOG) {
                    error_log("Broken links: Could not probe url " . $url . ", link is considerd broken [" . $curlErrorNo. " - " . $curlError . "]"); 
                }
                return false; // Do log
            }
        }

        if(defined("BROKEN_LINKS_LOG") && BROKEN_LINKS_LOG) {
            error_log("Broken links: Probe data " . $url . " [Curl error no: " . $curlErrorNo. "] [Curl error message:" . $curlError . "] [Http code: ".$httpCode."]"); 
        }
        
        //Validate
        if($response) {
            //Generic codes 
            if($httpCode >= 200 && $httpCode < 400) {
                return true; 
            }

            //Specific out of scope codes 
            //401: Unathorized
            //406: Not acceptable
            //413: Payload to large
            //418: I'm a teapot
            if(in_array($httpCode, array(401, 406, 413))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test if URL is internal and page exist
     * @param string $url Url to check
     * @return bool
     */
    public function isInternal($url)
    {
        // Check if post exist by url (only works with 'public' posts)
        $postId = url_to_postid($url);
        if ($postId > 0) {
            return true;
        }

        // Check if the URL is internal or external
        $siteUrlComponents = parse_url(get_site_url());
        $urlComponents = parse_url($url);
        if (!empty($siteUrlComponents['host']) && !empty($urlComponents['host']) && strcasecmp($urlComponents['host'], $siteUrlComponents['host']) === 0) {
            // Test with get_page_by_path() to get other post statuses
            $postTypes = get_post_types(array('public' => true));
            if (!empty($urlComponents['path']) && !empty(get_page_by_path(basename(untrailingslashit($urlComponents['path'])), ARRAY_A, $postTypes))) {
                return true;
            }
        }

        return false;
    }
}
