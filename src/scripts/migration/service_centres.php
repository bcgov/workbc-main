<?php

require('utilities.php');

/**
 * Create nodes for WorkBC Service Centres.
 *
 * Usage: drush scr scripts/migration/service_centres.php
 */

$file = __DIR__ . '/data/service_centres.csv';
if (($handle = fopen($file, 'r')) === FALSE) {
    die("Could not open Service Centres spreadsheet $file" . PHP_EOL);
}
print("Importing Service Centres spreadsheet $file" . PHP_EOL);

// The columns we are interested in.
const COL_CENTRE_ID = 0;
const COL_TITLE = 1;
const COL_ADDRESS_1 = 2;
const COL_ADDRESS_2 = 3;
const COL_CITY = 4;
const COL_PROVINCE_ID = 5;
const COL_POSTAL_CODE = 6;
const COL_LAT = 7;
const COL_LON = 8;
const COL_STORE_FRONT = 9;
const COL_ENGLISH = 10;
const COL_FRENCH = 11;
const COL_PHONE = 12;
const COL_FAX = 13;
const COL_EMAIL = 14;
const COL_WEBSITE = 15;
const COL_OPENING_HOURS = 16;

$row_number = 0;
global $centres;
$centres = [];
while (($row = fgetcsv($handle)) !== FALSE) {
    // Skip first header row.
    $row_number++;
    if ($row_number < 2) continue;
}
fclose($handle);
