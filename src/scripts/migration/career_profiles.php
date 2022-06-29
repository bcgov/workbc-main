<?php

/**
 * Generate career profile nodes from SSoT entries.
 * Source: /wages (WorkBC_2021_Wage_Data)
 *
 * Usage: drush scr /scripts/migration/career_profiles
 *
 * Revert: drush entity:delete node --bundle=career_profile
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$ssot = rtrim(\Drupal::config('workbc')->get('ssot_url'), '/');
$client = new Client();
try {
  $response = $client->get($ssot . '/wages');
  $result = json_decode($response->getBody(), TRUE);
  foreach ($result as $profile) {
    $fields = [
      'type' => 'career_profile',
      'title' => $profile['occupation_title'],
      'noc' => $profile['noc'],
      'uid' => 1,
    ];
    print("Creating {$fields['title']}\n");
    $node = Drupal::entityTypeManager()
      ->getStorage('node')
      ->create($fields);
    $node->save();
  }
}
catch (RequestException $e) {
  print($e->getMessage());
}
