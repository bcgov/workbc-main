<?php

/**
 * Populate EPBC FYP taxonomy.
 *
 * As per ticket WBCAMS-930.
 */
function workbc_ssot_deploy_930_epbc_categories(&$sandbox = NULL) {
  if (!isset($sandbox['terms'])) {
    $result = ssot('fyp_categories_interests_taxonomy');
    if (empty($result)) {
      throw new Exception("[WBCAMS-930] Failed to query SSOT fyp_categories_interests_taxonomy.");
    }
    $sandbox['terms'] = json_decode($result->getBody());
    $sandbox['count'] = count($sandbox['terms']);
    $sandbox['categories'] = [];
  }

  $entry = array_shift($sandbox['terms']);
  if (!array_key_exists($entry->category, $sandbox['categories'])) {
    $term = Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->create([
        'vid' => 'epbc_categories',
        'name' => $entry->category,
      ]);
    $term->save();
    $sandbox['categories'][$entry->category] = [
      'tid' => $term->id(),
      'depth' => 0,
    ];
  }
  $term = Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->create([
      'vid' => 'epbc_categories',
      'name' => $entry->interest,
      'parent' => $sandbox['categories'][$entry->category]['tid'],
      'weight' => $sandbox['categories'][$entry->category]['depth'],
    ]);
  $term->save();
  $message = "Created term $entry->category / $entry->interest.";
  $sandbox['categories'][$entry->category]['depth']++;
  $sandbox['#finished'] = empty($sandbox['terms']) ? 1 : ($sandbox['count'] - count($sandbox['terms'])) / $sandbox['count'];
  return t("[WBCAMS-930] $message");
}

/**
 * Populate CST occupational category taxonomy.
 *
 * As per ticket WBCAMS-923.
 */
function workbc_ssot_deploy_923_cst_categories(&$sandbox = NULL) {
  if (!isset($sandbox['terms'])) {
    $result = ssot('career_search_groups_taxonomy');
    if (empty($result)) {
      throw new Exception("[WBCAMS-923] Failed to query SSOT career_search_groups_taxonomy.");
    }
    $sandbox['terms'] = json_decode($result->getBody());
    $sandbox['count'] = count($sandbox['terms']);
  }

  $entry = array_shift($sandbox['terms']);
  $term = Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->create([
      'vid' => 'cst_categories',
      'name' => $entry->occupational_category,
      'field_region' => array_combine(array_values(ssotRegions()), array_values(ssotRegionIds()))[$entry->region]
    ]);
  $term->save();
  $message = "Created term $entry->occupational_category / $entry->region.";
  $sandbox['#finished'] = empty($sandbox['terms']) ? 1 : ($sandbox['count'] - count($sandbox['terms'])) / $sandbox['count'];
  return t("[WBCAMS-923] $message");
}
