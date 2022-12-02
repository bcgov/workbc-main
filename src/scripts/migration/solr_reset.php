<?php

/**
 * Reset all indexes from Solr core.
 */

$server = Drupal::entityTypeManager()
->getStorage('search_api_server')
->load('solr')
->getBackendConfig();

$endpoint = sprintf('%s://%s:%s/solr/%s/update?commit=true',
    $server['connector_config']['scheme'],
    $server['connector_config']['host'],
    $server['connector_config']['port'],
    $server['connector_config']['core'],
);
$output = null;
$code = null;
exec("curl -s '$endpoint' -H 'Content-Type: text/xml' --data-binary '<delete><query>*:*</query></delete>'", $output, $code);
print_r(join("\n", $output));
