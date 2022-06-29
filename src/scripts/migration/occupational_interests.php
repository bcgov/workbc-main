<?php

/**
 * Generate occupational interests taxonomy.
 * Source: occupational_interests.csv (https://www.workbc.ca/Labour-Market-Industry/Skills-for-the-Future-Workforce.aspx#characteristics)
 *
 * Usage: drush scr /scripts/migration/occupational_interests -- /path/to/occupational_interests.csv
 *
 * Revert: drush entity:delete taxonomy_term --bundle=occupational_interests
 */

$file = array_key_exists(0, $extra) ? $extra[0] : __DIR__ . '/occupational_interests.csv';
if (empty($file) or ($handle = fopen($file, "r")) === FALSE) {
    die("[WorkBC Migration] Could not open $file\nUsage: drush scr occupational_interests.php -- /path/to/occupational_interests.csv\n");
}
print("Importing $file\n");

// The columns we are interested in.
const TITLE = 0;
const SUBTITLE = 1;
const DESCRIPTION = 2;

while (($data = fgetcsv($handle)) !== FALSE) {
    $fields = [
        'vid' => 'occupational_interests',
        'name' => $data[TITLE],
        'field_subtitle' => $data[SUBTITLE],
        'description' => $data[DESCRIPTION],
    ];
    print("Creating {$fields['name']}\n");
    $term = Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->create($fields);
    $term->save();
}
