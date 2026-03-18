<?php

/**
 * Implements hook_views_data_alter().
 */
function workbc_extra_fields_views_data_alter(array &$data) {

  $data['node']['industry_profile_total_employment'] = array(
    'title' => t('Total Employment (Industry Profile'),
    'group' => t('Content'),
    'field' => array(
      'title' => t('Total Employment (Industry Profile)'),
      'help' => t('Displays total employment for Industry Profile.'),
      'id' => 'industry_profile_total_employment',
    ),
  );

  $data['node']['industry_profile_job_growth'] = array(
    'title' => t('Job Growth (Industry Profile)'),
    'group' => t('Content'),
    'field' => array(
      'title' => t('Job Growth (Industry Profile)'),
      'help' => t('Displays job growth for Industry Profile.'),
      'id' => 'industry_profile_job_growth',
    ),
  );

  $data['node']['industry_profile_job_growth_rate'] = array(
    'title' => t('Job Growth Rate (Industry Profile)'),
    'group' => t('Content'),
    'field' => array(
      'title' => t('Job Growth Rate (Industry Profile)'),
      'help' => t('Displays job growth rate for Industry Profile.'),
      'id' => 'industry_profile_job_growth_rate',
    ),
  );

  $data['node']['industry_profile_overview_source'] = array(
    'title' => t('Source: Labour Overview (Industry Profile)'),
    'group' => t('Content'),
    'field' => array(
      'title' => t('Source: Labour Overview (Industry Profile)'),
      'help' => t('Displays labour overview data source for Industry Profile.'),
      'id' => 'industry_profile_overview_source',
    ),
  );

}
