<?php
if (function_exists('acf_add_local_field_group')) {
  acf_add_local_field_group(array(
    'key' => 'group_5d19aaf9c929e',
    'title' => 'Content Insights for Editors',
    'fields' => array(
      array(
        'key' => 'field_5d19ab0cbbc00',
        'label' => __('Analyzed posttypes', 'content-insights-for-editors'),
        'name' => 'analysed_post_types',
        'type' => 'posttype_select',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '',
          'class' => '',
          'id' => '',
        ),
        'default_value' => '',
        'allow_null' => 1,
        'multiple' => 1,
        'placeholder' => '',
        'disabled' => 0,
        'readonly' => 0,
      ),
      array(
        'key' => 'field_5d19cd83d7edf',
        'label' => __(
          'Include private pages',
          'content-insights-for-editors'
        ),
        'name' => 'include_private_pages',
        'type' => 'true_false',
        'instructions' => __(
          'By default data for private pages is excluded, this option allows it to be displayed.',
          'content-insights-for-editors'
        ),
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '',
          'class' => '',
          'id' => '',
        ),
        'message' => '',
        'default_value' => 0,
        'ui' => 0,
        'ui_on_text' => '',
        'ui_off_text' => '',
      ),
      array(
        'key' => 'field_5d19cd83d7edd',
        'label' => __(
          'Use alternative user field',
          'content-insights-for-editors'
        ),
        'name' => 'use_alternate_user_field',
        'type' => 'true_false',
        'instructions' => __(
          'Uses the Editor field (page_meta_maineditor) for user filtering, instead of Author used by default.',
          'content-insights-for-editors'
        ),
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '',
          'class' => '',
          'id' => '',
        ),
        'message' => '',
        'default_value' => 0,
        'ui' => 0,
        'ui_on_text' => '',
        'ui_off_text' => '',
      ),
      array(
        'key' => 'field_5d19cd83d4ea3',
        'label' => __(
          'Enable automatic mailing for broken links',
          'content-insights-for-editors'
        ),
        'name' => 'use_broken_links_cron',
        'type' => 'true_false',
        'instructions' => __(
          'Sends email to all authors, editors and administrators every two weeks with a report on their broken links.',
          'content-insights-for-editors'
        ),
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '',
          'class' => '',
          'id' => '',
        ),
        'message' => '',
        'default_value' => 0,
        'ui' => 0,
        'ui_on_text' => '',
        'ui_off_text' => '',
      ),
      array(
        'key' => 'field_5d1c8f1232ba5',
        'label' => __('Obsolete pages', 'content-insights-for-editors'),
        'name' => 'ws_minimum_last_updated_threshold',
        'type' => 'number',
        'instructions' => __(
          'Set the threshold for how many days a page that has not been updated should be before the plugin flags the page as outdated.',
          'content-insights-for-editors'
        ),
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '25',
          'class' => '',
          'id' => '',
        ),
        'default_value' => '180',
        'placeholder' => '',
        'prepend' => '',
        'append' => __('days', 'content-insights-for-editors'),
        'min' => 5,
        'step' => 1,
      ),
      array(
        'key' => 'field_5d1c8cd534387',
        'label' => '<h1>Matomo</h1>',
        'name' => '',
        'type' => 'message',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '',
          'class' => '',
          'id' => '',
        ),
        'message' => '',
        'new_lines' => 'wpautop',
        'esc_html' => 0,
      ),
      array(
        'key' => 'field_5d1c8d1235ea1',
        'label' => 'Matomo Api URL',
        'name' => 'matomo_api_url',
        'type' => 'text',
        'instructions' => __(
          'ex. https://analys.yourdomain.se/',
          'content-insights-for-editors'
        ),
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '',
          'class' => '',
          'id' => '',
        ),
        'default_value' => '',
        'placeholder' => '',
        'prepend' => '',
        'append' => '',
        'maxlength' => '',
      ),
      array(
        'key' => 'field_5d1c8d2b35ea2',
        'label' => __('Matomo Api key', 'content-insights-for-editors'),
        'name' => 'matomo_api_key',
        'type' => 'text',
        'instructions' => __(
          'Visit admin for your Matomo installation. Under Platform -> API there is & token_auth = xxxxxxxxxxxxxxx. Paste the value corresponding to xxxxxxxxxxxxxxx',
          'content-insights-for-editors'
        ),
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '',
          'class' => '',
          'id' => '',
        ),
        'default_value' => '',
        'placeholder' => '',
        'prepend' => '',
        'append' => '',
        'maxlength' => '',
      ),
      array(
        'key' => 'field_5d1c8d3e35ea3',
        'label' => __('Matomo site id', 'content-insights-for-editors'),
        'name' => 'matomo_id_site',
        'type' => 'number',
        'instructions' => '',
        'required' => 0,
        'conditional_logic' => 0,
        'wrapper' => array(
          'width' => '',
          'class' => '',
          'id' => '',
        ),
        'default_value' => 1,
        'placeholder' => '',
        'prepend' => '',
        'append' => '',
        'min' => '',
        'max' => '',
        'step' => '',
      ),
    ),
    'location' => array(
      array(
        array(
          'param' => 'options_page',
          'operator' => '==',
          'value' => 'content-insights-for-editors-page-settings',
        ),
      ),
    ),
    'menu_order' => 0,
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'top',
    'instruction_placement' => 'label',
    'hide_on_screen' => '',
    'active' => 1,
    'description' => '',
  ));

  if (class_exists('\CustomerFeedback\App')) {
    acf_add_local_field(array(
      'parent' => 'group_5d19aaf9c929e',
      'key' => 'field_5d1c8cd5343f2',
      'label' => '<h1>Customer Feedback</h1>',
      'name' => '',
      'type' => 'message',
      'instructions' => '',
      'required' => 0,
      'conditional_logic' => 0,
      'wrapper' => array(
        'width' => '',
        'class' => '',
        'id' => '',
      ),
      'message' => '',
      'new_lines' => 'wpautop',
      'esc_html' => 0,
    ));

    acf_add_local_field(array(
      'parent' => 'group_5d19aaf9c929e',
      'key' => 'field_5d1c8a1212bf6',
      'label' => __(
        'Customer feedback minimum responses',
        'content-insights-for-editors'
      ),
      'name' => 'ws_minimum_feedback_responses',
      'type' => 'number',
      'instructions' => __(
        'Set the threshold for how votes (yes/no) the page must have since last update.',
        'content-insights-for-editors'
      ),
      'required' => 0,
      'conditional_logic' => 0,
      'wrapper' => array(
        'width' => '25',
        'class' => '',
        'id' => '',
      ),
      'default_value' => '5',
      'placeholder' => '',
      'prepend' => '',
      'append' => __('votes', 'content-insights-for-editors'),
      'min' => 5,
      'step' => 1,
    ));
  }
}
