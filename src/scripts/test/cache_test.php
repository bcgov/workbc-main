#!/usr/bin/env php
<?php
/**
 * Iterate over a list of URLs and capture response headers into a CSV.
 *
 * Usage:
 * - export BASE_URL=https://www.workbc.ca
 * - wget -q $BASE_URL/sitemap.xml -O sitemap.xml
 * - xmllint --xpath "//*[local-name()='loc']/text()" sitemap.xml > urls.txt
 * - ./cache_test.php urls.txt > cache-log.csv
 */
$urls = $argv[1];
$assets = [];
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
  "X-Amz-Cf-Id",
  "X-Amz-Cf-Pop"
];
if (!getenv('BASE_URL')) {
  fwrite(STDERR, "Warning: BASE_URL is not defined. Assets won't be fetched.\n");
}

// $pages = 0;

$handle = fopen($urls, "r");
if ($handle) {
  fputcsv(STDOUT, array_merge(["URL"], $headers));
  while (($url = trim(fgets($handle))) !== false) {
    // Call each page twice to exercise the cache.
    request($url, $headers, $assets);
    request($url, $headers, $assets);

    // if ($pages++ > 3) break;
  }
  fclose($handle);
}
if (getenv('BASE_URL')) foreach ($assets as $asset) {
  if (preg_match('/^\\/[a-z]/i', $asset, $matches) > 0) {
    $url = getenv('BASE_URL') . $asset;
    request($url, $headers);
    request($url, $headers);
  }
}

function request($url, $headers, &$assets = null) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HEADER, 1);
  $response = curl_exec($ch);
  if (empty($response)) {
    fwrite(STDERR, $url . ": " . curl_error($ch) . "\n");
    return;
  }
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $response_headers = get_headers_from_curl_response(substr($response, 0, $header_size));
  fputcsv(STDOUT, array_merge([$url], array_map(function($header) use ($response_headers) {
    return @$response_headers[strtolower($header)];
  }, $headers)));

  if (!is_null($assets)) {
    $body = substr($response, $header_size);
    $dom = new DOMDocument();
    $dom->loadHTML($body, LIBXML_NOWARNING | LIBXML_NOERROR);
    foreach ($dom->getElementsByTagName('link') as $link) {
      $asset = $link->getAttribute('href');
      if (!in_array($asset, $assets)) $assets[] = $asset;
    }
    foreach ($dom->getElementsByTagName('script') as $link) {
      $asset = $link->getAttribute('src');
      if (!in_array($asset, $assets)) $assets[] = $asset;
    }
    foreach ($dom->getElementsByTagName('img') as $link) {
      $asset = $link->getAttribute('src');
      if (!in_array($asset, $assets)) $assets[] = $asset;
    }
  }

  curl_close($ch);
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
