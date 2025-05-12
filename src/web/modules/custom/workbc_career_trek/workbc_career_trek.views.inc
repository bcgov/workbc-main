<?php

/**
 * @file
 * Integration with Views.
 */
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Implements hook_views_data_alter().
 */

function workbc_career_trek_views_data_alter(array &$data) {
  foreach($data as $table_name => $table_data) {
    foreach($table_data as $field_name => $field_data) {
        if (isset($field_data['field']['id']) && $field_data['field']['id'] == "search_api" && $field_name == "skills") {
            $data[$table_name][$field_name]['filter']['id'] = 'views_select_filter_string';
        }
    }
  }
}