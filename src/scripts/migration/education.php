<?php

/**
 * Populate education taxonomy.
 * Source: /education (All Occupation's Education Background 2021.xlsx)
 *
 * Usage: drush scr scripts/migration/education
 *
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$ssot = rtrim(\Drupal::config('workbc')->get('ssot_url'), '/');
$client = new Client();
try {
  // TODO Figure out a way to use DISTINCT or GROUP BY in the API query.
  $response = $client->get($ssot . '/education?select=typical_education_background&order=typical_education_background');
  $result = json_decode($response->getBody(), TRUE);
  $educations = array_unique(array_map(fn($r) => $r['typical_education_background'], $result));
  foreach ($educations as $education) {
    $fields = [
      'vid' => 'education',
      'name' => $education,
    ];
    print("Creating {$fields['name']}\n");
    $term = Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->create($fields);
    $term->save();
  }
}
catch (RequestException $e) {
  print($e->getMessage());
}
