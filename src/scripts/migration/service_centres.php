<?php

require('utilities.php');

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Create nodes for WorkBC Service Centres.
 *
 * Usage: drush scr scripts/migration/service_centres.php
 */

$file = __DIR__ . '/data/service_centres.kml';
if (!file_exists($file)) {
    die("Could not open Service Centres KML $file" . PHP_EOL);
}
print("Importing Service Centres KML $file" . PHP_EOL);

// FIRST PASS: Create all the nodes.
print("FIRST PASS =================" . PHP_EOL);

$xml = simplexml_load_file($file);
$xml->registerXPathNamespace('kml', "http://www.opengis.net/kml/2.2");

foreach ($xml->Document->Placemark as $centre) {
    $title = convertPlainText($centre->name);
    print("Processing \"$title\"..." . PHP_EOL);

    // Build the fields.
    $fields = [
        'type' => 'workbc_centre',
        'title' => $title,
        'field_email' => str_replace('mailto:', '', $centre->xpath('kml:ExtendedData/kml:Data[@name="email"]')[0]->value),
        // 'field_website' => [
        //     'title' => 'Visit Website',
        //     'uri' => // TODO,
        //     'options' => [
        //         'attributes' => [
        //             'rel' => 'noopener noreferrer',
        //             'target' => '_blank',
        //         ]
        //     ]
        // ],
        'field_working_hours' => convertWorkingHours($centre),
        'field_geolocation' => convertCoordinates($centre),
        'field_french_available' => strcasecmp('Y', $centre->xpath('kml:ExtendedData/kml:Data[@name="hasFrench"]')[0]->value) === 0,
        'field_address' => convertAddress($centre),
        'field_phone' => (string) $centre->xpath('kml:ExtendedData/kml:Data[@name="phone"]')[0]->value,
    ];

    $node = createNode($fields, (string) $centre->xpath('kml:ExtendedData/kml:Data[@name="website"]')[0]->value);
}

function convertWorkingHours($centre) {
    return [
        'value' => join('<br/>', array_filter([
            (string) $centre->xpath('kml:ExtendedData/kml:Data[@name="hours1"]')[0]->value,
            (string) $centre->xpath('kml:ExtendedData/kml:Data[@name="hours2"]')[0]->value,
            (string) $centre->xpath('kml:ExtendedData/kml:Data[@name="hours3"]')[0]->value,
            (string) $centre->xpath('kml:ExtendedData/kml:Data[@name="hours4"]')[0]->value,
        ])),
        'format' => 'full_html',
    ];
}

function convertCoordinates($centre) {
    $coords = explode(',', $centre->Point->coordinates);
    return [
        'lat' => $coords[0],
        'lng' => $coords[1],
    ];
}

function convertAddress($centre) {
    $address = explode(',', (string) $centre->xpath('kml:ExtendedData/kml:Data[@name="address"]')[0]->value);
    if (count($address) < 3 || count($address) > 4) {
        print("  Error: Could not parse address" . PHP_EOL);
    }
    if (count($address) == 3) {
        array_splice($address, 2, 0, '');
    }
    return [
        'country_code' => 'CA',
        'address_line1' => trim($address[0]),
        'address_line2' => trim($address[2]),
        'administrative_area' => 'BC',
        'locality' => trim($address[1]),
        'postal_code' => trim(str_replace('BC', '', $address[3])),
    ];
}
