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
const COL_CATCHMENT_AREA_ID = 17;
const COL_CONTRACTOR_ID = 18;
const COL_LEGACY_URL = 19;

// FIRST PASS: Create all the nodes.
print("FIRST PASS =================" . PHP_EOL);

$row_number = 0;
global $centres;
$centres = [];
while (($row = fgetcsv($handle)) !== FALSE) {
    // Skip first header row.
    $row_number++;
    if ($row_number < 2) continue;

    $title = convertPlainText($row[COL_TITLE]);
    print("Processing \"$title\"..." . PHP_EOL);

    // Build the fields.
    $fields = [
        'type' => 'workbc_centre',
        'title' => $title,
        'field_email' => $row[COL_EMAIL],
        'field_website' => [
            'title' => 'Visit Website',
            'uri' => $row[COL_WEBSITE],
            'options' => [
                'attributes' => [
                    'rel' => 'noopener noreferrer',
                    'target' => '_blank',
                ]
            ]
        ],
        'field_working_hours' => convertWorkingHours($row[COL_OPENING_HOURS]),
        'field_geolocation' => [
            'lat'=> $row[COL_LAT],
            'lng' => $row[COL_LON]
        ],
        'field_french_available' => !!$row[COL_FRENCH],
        'field_address' => [
            'country_code' => 'CA',
            'address_line1' => $row[COL_ADDRESS_1],
            'administrative_area' => 'BC',
            'locality' => $row[COL_CITY],
            'postal_code' => $row[COL_POSTAL_CODE],
        ],
        'field_phone' => $row[COL_PHONE],
    ];

    $node = createNode($fields, $row[COL_LEGACY_URL] . '?id=' . $row[COL_CENTRE_ID]);
}
fclose($handle);

function convertWorkingHours($hours) {
    return [
        'format' => 'full_html',
        'value' => str_replace("\n", "<br/>", $hours),
    ];
}
