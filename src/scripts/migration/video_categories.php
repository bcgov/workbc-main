<?php

/**
 * Generate video categories taxonomy.
 * Source: video_categories.csv (https://www.workbc.ca/videolibrary/)
 *
 * Usage: drush scr /scripts/migration/video_categories -- /path/to/video_categories.csv
 *
 * Revert: drush entity:delete taxonomy_term --bundle=video_categories
 */

$file = array_key_exists(0, $extra) ? $extra[0] : __DIR__ . '/video_categories.csv';
if (empty($file) or ($handle = fopen($file, "r")) === FALSE) {
    die("[WorkBC Migration] Could not open $file\nUsage: drush scr video_categories.php -- /path/to/video_categories.csv\n");
}
print("Importing $file\n");

// The columns we are interested in.
const TITLE = 0;
const DESCRIPTION = 1;

while (($data = fgetcsv($handle)) !== FALSE) {
    $fields = [
        'vid' => 'video_categories',
        'name' => $data[TITLE],
        'description' => $data[DESCRIPTION],
    ];
    print("Creating {$fields['name']}\n");
    $term = Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->create($fields);
    $term->save();
}
