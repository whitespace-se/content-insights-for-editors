<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Admin;

use CONTENT_INSIGHTS_FOR_EDITORS\BrokenLinks\ExternalLinkCheckConfig;

class Settings {
  public function __construct() {
    add_action('admin_menu', array($this, 'init'));

    add_filter('cife_posts_where_statments', array($this, 'addSettingsValues'));
    add_filter(
      'acf/load_field/key=field_5d19ab0cbbc00',
      array($this, 'prepareAnalysedPostTypesField')
    );
    add_filter(
      'acf/load_field/key=field_cife_automatic_external_link_checks',
      array($this, 'prepareAutomaticExternalChecksField')
    );
  }

  public function prepareAutomaticExternalChecksField($field) {
    $field['message'] = self::automaticExternalCheckSummary();
    return $field;
  }

  public function prepareAnalysedPostTypesField($field) {
    $choices = array();
    $excluded = self::getExcludedPostTypes();
    foreach ((array) get_post_types(array('public' => true), 'objects') as $postType) {
      if (!is_object($postType) || in_array($postType->name, $excluded, true)) {
        continue;
      }
      $choices[$postType->name] = $postType->label;
    }

    $field['type'] = 'select';
    $field['choices'] = $choices;
    $field['multiple'] = 1;
    $field['allow_null'] = 1;
    $field['ui'] = 1;
    $field['ajax'] = 0;
    return $field;
  }

  public function addSettingsValues($where_posts) {
    $post_types = self::getSelectedPostTypes();

    if (is_array($post_types) && count($post_types) > 0) {
      $where_posts['post_type'] = $post_types;
    }

    return $where_posts;
  }

  public static function validateAndGetLastUpdatedThreshold() {
    $threshold = get_field('ws_minimum_last_updated_threshold', 'options');
    return is_numeric($threshold) && $threshold > 0 ? $threshold : false;
  }

  public static function validateAndGetExternalLinkReviewDays() {
    $days = get_field('cife_external_link_review_days', 'options');
    return is_numeric($days) && (int) $days > 0 ? (int) $days : 30;
  }

  public static function validateAndGetMinFeedbackResponsesThreshold() {
    $threshold = get_field('ws_minimum_feedback_responses', 'options');
    return is_numeric($threshold) && $threshold > 0 ? $threshold : 5;
  }

  public static function getSelectedPostTypes() {
    $postTypes = get_field('analysed_post_types', 'options');
    if ($postTypes && is_array($postTypes)) {
      foreach ($postTypes as $postType) {
        if (empty($postType)) {
          return false;
        }
      }
      $postTypes = array_values(array_unique(array_map('sanitize_key', $postTypes)));
      $postTypes = array_values(array_diff($postTypes, self::getExcludedPostTypes()));
      return $postTypes ?: false;
    }
    return false;
  }

  public static function getAnalysedPostTypes() {
    $postTypes = self::getSelectedPostTypes();
    if ($postTypes) {
      return array_values(array_unique(array_map('sanitize_key', $postTypes)));
    }

    $postTypes = get_post_types(array('public' => true), 'names');
    return array_values(array_diff((array) $postTypes, self::getExcludedPostTypes()));
  }

  public static function getExcludedPostTypes() {
    $postTypes = (array) apply_filters(
      'cife_excluded_analysed_post_types',
      array('attachment', 'revision', 'external_page', 'modal-content')
    );
    return array_values(array_unique(array_map('sanitize_key', $postTypes)));
  }

  public static function getUseAlternateUserField() {
    return boolval(get_field('use_alternate_user_field', 'options'));
  }

  public static function getMatomoApiUrl() {
    return get_field('matomo_api_url', 'options');
  }

  public static function getMatomoApiToken() {
    return get_field('matomo_api_key', 'options');
  }

  public static function getMatomoIdSite() {
    return get_field('matomo_id_site', 'options');
  }

  public static function automaticExternalCheckSummary() {
    $config = new ExternalLinkCheckConfig();
    if (!$config->isEnabled()) {
      return '<div class="cife-readonly-config"><p><strong>' .
        esc_html__('Disabled', 'content-insights-for-editors') .
        '</strong></p><p>' . esc_html__(
          'No hosts have been approved for automatic external-link checks on this installation.',
          'content-insights-for-editors'
        ) . '</p></div>';
    }

    $hosts = implode(', ', $config->getHosts());
    return '<div class="cife-readonly-config"><p><strong>' .
      esc_html__('Enabled', 'content-insights-for-editors') .
      '</strong></p><dl>' .
      '<dt>' . esc_html__('Approved hosts', 'content-insights-for-editors') . '</dt><dd>' .
      esc_html($hosts) . '</dd>' .
      '<dt>' . esc_html__('User agent', 'content-insights-for-editors') . '</dt><dd><code>' .
      esc_html($config->getUserAgent()) . '</code></dd>' .
      '<dt>' . esc_html__('Check interval', 'content-insights-for-editors') . '</dt><dd>' .
      esc_html(sprintf(
        /* translators: %d: number of days between automatic checks. */
        _n('%d day', '%d days', $config->getIntervalDays(), 'content-insights-for-editors'),
        $config->getIntervalDays()
      )) . '</dd>' .
      '<dt>' . esc_html__('Maximum request rate', 'content-insights-for-editors') . '</dt><dd>' .
      esc_html(sprintf(
        /* translators: %d: maximum number of requests per minute. */
        _n('%d request per minute', '%d requests per minute', $config->getRequestsPerMinute(), 'content-insights-for-editors'),
        $config->getRequestsPerMinute()
      )) . '</dd></dl><p class="description">' . esc_html__(
        'This deployment-controlled configuration is read-only here. CIFE never crawls approved sites; it checks only exact links already found in analysed content.',
        'content-insights-for-editors'
      ) . '</p></div>';
  }

  public function init() {
    if (function_exists('acf_add_options_page')) {
      acf_add_options_sub_page(array(
        'page_title' => sprintf(
          '%s - %s',
          __('Settings', 'content-insights-for-editors'),
          \CONTENT_INSIGHTS_FOR_EDITORS\App::$pluginTitle
        ),
        'menu_title' => sprintf(
          '%s - %s',
          __('Settings', 'content-insights-for-editors'),
          \CONTENT_INSIGHTS_FOR_EDITORS\App::$pluginTitle
        ),
        'menu_slug' => Main::$MENU_SLUG . '-settings',
        'parent_slug' => Main::$MENU_SLUG,
        'capability' => 'manage_options',
      ));
    }
  }
}
