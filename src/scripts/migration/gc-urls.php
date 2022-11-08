<?php

/**
 * Extract URLs for GC assets or PDF files from GC JSONL files.
 *
 * Usage: php gc-urls.php [--pdf | --assets] < /path/to/input.jsonl > /path/to/output
 */

const ASSET_REGEX = '|https://assets.gathercontent.com/[A-Za-z0-9\\/]+\\?dl=[^"]+|';
const PDF_REGEX = '|https://www.workbc.ca/getmedia/[^"]+.pdf.aspx|';

$opts = getopt('', [
    'pdf',
    'assets',
]);
$usage = 'Usage: php gc-urls.php [--pdf | --assets] < /path/to/input.jsonl > /path/to/output';
if (!(array_key_exists('pdf', $opts) xor array_key_exists('assets', $opts))) {
    die("Select either pdf or assets to download\n$usage" . PHP_EOL);
}

stream_set_blocking(STDIN, TRUE);
while (($line = fgets(STDIN)) !== false) {
    $json = json_decode($line);
    $matches = [];
    preg_match_all(array_key_exists('pdf', $opts) ? PDF_REGEX : ASSET_REGEX, json_encode($json, JSON_UNESCAPED_SLASHES), $matches);
    foreach ($matches as $match) {
        foreach ($match as $m) {
            fwrite(STDOUT, $m . PHP_EOL);
        }
    }
}
