#!/usr/bin/env php
<?php
/**
 * Iterate over a list of URLs and capture response headers into a CSV.
 *
 * Usage:
 * - wget -q $BASE_URL/sitemap.xml -O sitemap.xml
 * - xmllint --xpath "//*[local-name()='loc']/text()" sitemap.xml > urls.txt
 * - ./cache_test.php urls.txt > cache-log.csv
 */
$urls = $argv[1];
$handle = fopen($urls, "r");
$headers = [
  "Age",
  "Cache-Control",
  "Expires",
  "X-Drupal-Cache",
  "X-Drupal-Dynamic-Cache",
  "X-Drupal-Cache-Max-Age",
  "X-Drupal-Cache-Contexts",
  "X-Drupal-Cache-Tags",
  "x-served-by",
  "x-cache",
  "x-cache-hits",
  "cf-cache-status",
  "cf-ray",
  "cf-request-id",
];
if ($handle) {
  fputcsv(STDOUT, array_merge(["URL"], $headers));
  while (($url = trim(fgets($handle))) !== false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $response = curl_exec($ch);
    if (empty($response)) {
      fwrite(STDERR, curl_error($ch) . "\n");
      continue;
    }
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $response_headers = get_headers_from_curl_response(substr($response, 0, $header_size));
    fputcsv(STDOUT, array_merge([$url], array_map(function($header) use ($response_headers) {
      return @$response_headers[strtolower($header)];
    }, $headers)));
    curl_close($ch);
  }
  fclose($handle);
}

// https://stackoverflow.com/a/10590242/209184
function get_headers_from_curl_response($content) {
  $headers = [];
  foreach (explode("\r\n", $content) as $i => $line)
  {
    if ($i === 0) {
      $headers['http_code'] = $line;
    } else {
      $header = explode(': ', $line);
      if (count($header) > 1) {
        list ($key, $value) = $header;
        $headers[strtolower($key)] = $value;
      }
    }
  }
  return $headers;
}
