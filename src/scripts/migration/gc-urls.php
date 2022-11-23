<?php

/**
 * Extract URLs for downloadable assets from GC JSONL files.
 *
 * Usage: php gc-urls.php [--images | --files] < /path/to/input.jsonl > /path/to/output
 */

const GC_IMAGE_REGEX = '|https://assets.gathercontent.com/[A-Za-z0-9\\/]+\\?dl=[^"]+|';
const WORKBC_IMAGE_REGEX = '/https:\/\/www.workbc.ca\/getmedia\/[[:alnum:]-]+\/[^"#?]+.jpg.aspx|https:\/\/www.workbc.ca\/getattachment\/[[:alnum:]\/-]+\/[^"#?]+.jpg.aspx/';
const WORKBC_FILE_REGEX = '/https:\/\/www.workbc.ca\/getmedia\/[[:alnum:]-]+\/[^"#]+.(?:pdf|docx).aspx/';

$opts = getopt('', [
    'files',
    'images',
]);
$usage = 'Usage: php gc-urls.php [--images | --files] < /path/to/input.jsonl > /path/to/output';
if (!(array_key_exists('images', $opts) xor array_key_exists('files', $opts))) {
    die("Select either image or file assets to extract\n$usage" . PHP_EOL);
}

stream_set_blocking(STDIN, TRUE);
while (($line = fgets(STDIN)) !== false) {
    $json = json_decode($line);
    $matches = [];
    if (array_key_exists('files', $opts)) {
        preg_match_all(WORKBC_FILE_REGEX, json_encode($json, JSON_UNESCAPED_SLASHES), $matches);
    }
    else {
        preg_match_all(GC_IMAGE_REGEX, json_encode($json, JSON_UNESCAPED_SLASHES), $matches1);
        preg_match_all(WORKBC_IMAGE_REGEX, json_encode($json, JSON_UNESCAPED_SLASHES), $matches2);
        $matches = array_merge($matches1, $matches2);
    }
    foreach ($matches as $match) {
        foreach ($match as $m) {
            fwrite(STDOUT, $m . PHP_EOL);
        }
    }
}
