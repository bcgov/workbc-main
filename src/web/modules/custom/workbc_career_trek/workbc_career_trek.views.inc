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
    'group' => 'Media',
    'filter' => [
      'help' => 'Provides a way to use Search API in a media view.',
      'field' => 'mid',
      'id' => 'workbc_media_keyword_search',
    ],
    'sort' => [
      'help' => 'Provides a way to sort the media according to Search API result ordering.',
      'field' => 'mid',
      'id' => 'workbc_media_keyword_search',
    ],
  ];

  $data['node_field_data']['career_categories'] = [
    'title' => 'WorkBC Career Categories',
    'group' => 'Content',
    'field' => [
      'id' => 'workbc_career_categories',
    ]
  ];
}
