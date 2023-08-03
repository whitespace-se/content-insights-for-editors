<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS;

class InternalDetector
{
    public $postId;

    public $permalinkBefore;
    public $permalinkAfter;
    public $trashed = false;

    public $permalinksUpdated = 0;

    public function __construct($data, $postarr)
    {
        if (!in_array($postarr['post_type'], array('revision', 'attachment'))) {
            $this->postId = (int) $postarr['ID'];
            $this->getPermalinkBefore($this->postId);
            add_action('save_post', array($this, 'getPermalinkAfter'), 10, 2);
        }
    }

    /**
     * Get permalink before save
     * @param  integer $postId Post id
     * @return void
     */
    public function getPermalinkBefore($postId)
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        $this->permalinkBefore = get_permalink($postId);
    }

    /**
     * Get new permalink (after save)
     * @param  integer $postId Post id
     * @return void
     */
    public function getPermalinkAfter($postId, $post)
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        remove_action('save_post', array($this, 'getPermalinkAfter'), 10, 2);

        $this->permalinkAfter = get_permalink($this->postId);

        if ($post->post_status === 'trash') {
            $this->trashed = true;
        }

        if ($this->permalinkBefore && !empty($this->permalinkBefore)) {
            $this->detectChangedPermalink();
        }
    }

    /**
     * Detect and repair
     * @return void
     */
    public function detectChangedPermalink()
    {
        // if permalink not changed, return, do nothing more
        if ($this->permalinkBefore === $this->permalinkAfter && !$this->trashed) {
            return false;
        }

        if ($this->trashed) {
            App::$externalDetector->lookForBrokenLinks('internal', str_replace('__trashed', '', $this->permalinkBefore));
            return true;
        }

        // Replace occurances of the old permalink with the new permalink
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $wpdb->posts
                    SET post_content = REPLACE(post_content, %s, %s)
                    WHERE post_content LIKE %s",
                $this->permalinkBefore,
                $this->permalinkAfter,
                '%' . $wpdb->esc_like($this->permalinkBefore) . '%'
            )
        );

        $this->permalinksUpdated += $wpdb->rows_affected;

        if ($this->permalinksUpdated > 0) {
            add_notice(sprintf('%d links to this post was updated to use the new permalink.', $this->permalinksUpdated));
        }

        return true;
    }
}
