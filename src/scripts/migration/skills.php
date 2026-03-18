<?php

/**
 * Populate skills taxonomy.
 * Source: /skills (UPDATED FINAL Skills Data for Career Profiles (updated April16 19).xlsx)
 *
 * Usage: drush scr scripts/migration/skills
 *
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$ssot = rtrim(\Drupal::config('workbc')->get('ssot_url'), '/');
$client = new Client();
try {
  // TODO Figure out a way to use DISTINCT or GROUP BY in the API query.
  $response = $client->get($ssot . '/skills?select=skills_competencies&order=skills_competencies');
  $result = json_decode($response->getBody(), TRUE);
  $skills = array_unique(array_map(fn($r) => $r['skills_competencies'], $result));
  foreach ($skills as $skill) {
    $fields = [
      'vid' => 'skills',
      'name' => $skill,
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
