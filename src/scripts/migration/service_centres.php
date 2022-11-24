<?php

require('utilities.php');

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Create nodes for WorkBC Service Centres.
 *
 * Usage: drush scr scripts/migration/service_centres.php
 */

const COL_WorkBCServiceCentreID = 0;
const COL_CentreTitle = 1;
const COL_Address1 = 2;
const COL_Address2 = 3;
const COL_City = 4;
const COL_PostalCode = 5;
const COL_Phone = 6;
const COL_Email = 7;
const COL_Website = 8;
const COL_Hours1 = 9;
const COL_Hours2 = 10;
const COL_Hours3 = 11;
const COL_Hours4 = 12;
const COL_ServiceCentreLogo = 13;
const COL_Latitude = 14;
const COL_Longitude = 15;
const COL_RegionID = 16;
const COL_FrenchServiceID = 17;
const COL_Contractor = 18;
const COL_ESCCustomContent = 19;
const COL_ApplyOnlineHeaderCaption = 20;
const COL_ApplyOnlineBody = 21;
const COL_ApplyOnlineCTACaption = 22;
const COL_ApplyOnlineCTALink = 23;
const COL_LegacyURL = 24;

$file = __DIR__ . '/data/service_centres.csv';
if (($handle = fopen($file, 'r')) === FALSE) {
    die("Could not open Service Centres CSV $file" . PHP_EOL);
}
print("Importing Service Centres $file" . PHP_EOL);

// FIRST PASS: Create all the nodes.
print("FIRST PASS =================" . PHP_EOL);

$row_number = 0;
$centres = [];
while (($centre = fgetcsv($handle)) !== FALSE) {
    // Skip first header row.
    $row_number++;
    if ($row_number < 2) continue;

    $title = convertCellText($centre[COL_CentreTitle]);
    print("Processing \"$title\"..." . PHP_EOL);

    // Build the fields.
    $fields = [
        'type' => 'workbc_centre',
        'title' => 'WorkBC Centre - ' . $title,
        'field_email' => $centre[COL_Email],
        'field_website' => [
            'title' => 'Visit Website',
            'uri' => $centre[COL_Website],
            'options' => [
                'attributes' => [
                    'rel' => 'noopener noreferrer',
                    'target' => '_blank',
                ]
            ]
        ],
        'field_working_hours' => convertWorkingHours($centre),
        'field_geolocation' => convertCoordinates($centre),
        'field_address' => convertAddress($centre),
        'field_phone' => $centre[COL_Phone],
        'field_job_board_id' => convertCellText($centre[COL_City]),
    ];

    $node = createNode($fields, $centre[COL_LegacyURL]);

    // Remember service centres that offer translation.
    if (!empty($centre[COL_FrenchServiceID])) {
        $centres[$centre[COL_WorkBCServiceCentreID]] = [
            'nid' => $node->id(),
            'title' => $fields['title'],
            'legacy_id' => $centre[COL_WorkBCServiceCentreID],
            'translation_id' => $centre[COL_FrenchServiceID],
        ];
    }
}
fclose($handle);

// SECOND PASS: Link translated service centres.
print("SECOND PASS =================" . PHP_EOL);
foreach ($centres as $centre) {
    if (!array_key_exists($centre['translation_id'], $centres)) {
        print("  Error: Could not find translation centre {$centre['translation_id']}" . PHP_EOL);
        continue;
    }

    print("  Linking {$centre['title']}" . PHP_EOL);

    $node = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->load($centre['nid']);

    // Link to the other language centre.
    $node->field_multilingual_centre = [[
        // A hack to determine which language this current centre has: French nid > English nid in the CSV.
        'title' => $node->id() > $centres[$centre['translation_id']]['nid'] ? 'Services in English' : 'Services disponibles en français',
        'uri' => 'internal:/node/' . $centres[$centre['translation_id']]['nid'],
    ]];

    $node->save();
}

function convertCellText($cell) {
    $text = str_replace("\n", '<br/>', convertPlainText($cell));
    return strcasecmp('NULL', $text) === 0 ? NULL : $text;
}

function convertWorkingHours($centre) {
    return [
        'value' => join('<br/>', array_filter([
            convertCellText($centre[COL_Hours1]),
            convertCellText($centre[COL_Hours2]),
            convertCellText($centre[COL_Hours3]),
            convertCellText($centre[COL_Hours4]),
        ])),
        'format' => 'full_html',
    ];
}

function convertCoordinates($centre) {
    return [
        'lat' => $centre[COL_Latitude],
        'lng' => $centre[COL_Longitude],
    ];
}

function convertAddress($centre) {
    return [
        'country_code' => 'CA',
        'address_line1' => convertCellText($centre[COL_Address1]),
        'address_line2' => convertCellText($centre[COL_Address2]),
        'administrative_area' => 'BC',
        'locality' => convertCellText($centre[COL_City]),
        'postal_code' => convertCellText($centre[COL_PostalCode]),
    ];
}
