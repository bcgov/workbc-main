<?php

require('utilities.php');

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Create nodes for WorkBC Publications.
 *
 * Usage: drush scr scripts/migration/publications.php
 */

$file = __DIR__ . '/data/publications.csv';
if (($handle = fopen($file, 'r')) === FALSE) {
    die("Could not open Publications spreadsheet $file" . PHP_EOL);
}
print("Importing Publications spreadsheet $file" . PHP_EOL);

// The columns we are interested in.
const COL_ID = 0;
const COL_TITLE = 1;
const COL_FILE = 2;

// FIRST PASS: Create all the nodes.
print("FIRST PASS =================" . PHP_EOL);

$row_number = 0;
while (($row = fgetcsv($handle)) !== FALSE) {
    // Skip first header row.
    $row_number++;
    if ($row_number < 2) continue;

    $title = convertPlainText($row[COL_TITLE]);
    print("Processing \"$title\"..." . PHP_EOL);

    // Build the fields.
    $fields = [
        'type' => 'publication',
        'title' => $title,
        'field_hardcopy_available' => false,
    ];

    // Hardcopy publications have a resource number.
    if (!empty($row[COL_ID])) {
        $fields['field_resource_number'] = $row[COL_ID];
        $fields['field_hardcopy_available'] = true;
    }

    // Find the related file.
    $file = createFile(NULL, 'pdf', $row[COL_FILE], $row[COL_FILE]);
    if (empty($file)) continue;

    $fields['field_publication'] = [
        'target_id' => $file->id(),
    ];

    $node = createNode($fields);
}
fclose($handle);
