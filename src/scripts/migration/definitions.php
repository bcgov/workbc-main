<?php

/**
 * Generate definitions taxonomy.
 * Source: definitions.csv (https://www.workbc.ca/Jobs-Careers/Career-Toolkit/Definitions.aspx)
 *
 * Usage: drush scr /scripts/migration/definitions -- /path/to/definitions.csv
 *
 * Revert: drush entity:delete taxonomy_term --bundle=definitions
 */

$file = array_key_exists(0, $extra) ? $extra[0] : __DIR__ . '/definitions.csv';
if (empty($file) or ($handle = fopen($file, "r")) === FALSE) {
    die("[WorkBC Migration] Could not open $file\nUsage: drush scr definitions.php -- /path/to/definitions.csv\n");
}
print("Importing $file\n");

// The columns we are interested in.
const TITLE = 0;
const DESCRIPTION = 1;

while (($data = fgetcsv($handle)) !== FALSE) {
    $fields = [
        'vid' => 'definitions',
        'name' => $data[TITLE],
        'description' => $data[DESCRIPTION],
    ];
    print("Creating {$fields['name']}\n");
    $term = Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->create($fields);
    $term->save();
}
