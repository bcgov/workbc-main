<?php

/**
 * @file
 * Integration with Views.
 */

/**
 * Implements hook_views_data_alter().
 */
function workbc_career_trek_views_data_alter(array &$data) {

  $data['media']['keyword_search'] = [
    'title' => 'WorkBC Media Keyword Search',
    'group' => 'Content',
    'filter' => [
      'title' => 'WorkBC Media Keyword Search',
      'help' => 'Provides a way to use Search API in a media view.',
      'field' => 'mid',
      'id' => 'workbc_media_keyword_search',
    ],
    'sort' => [
      'title' => 'WorkBC Media Keyword Search',
      'help' => 'Provides a way to sort the media according to Search API result ordering.',
      'field' => 'mid',
      'id' => 'workbc_media_keyword_search',
    ],
  ];

  foreach($data as $table_name => $table_data) {
    foreach($table_data as $field_name => $field_data) {
        if (isset($field_data['field']['id']) && $field_data['field']['id'] == "search_api" && $field_name == "skills") {
            $data[$table_name][$field_name]['filter']['id'] = 'views_select_filter_string';
        }
    }
  }
}
