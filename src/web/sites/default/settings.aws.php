<?php

$base_urls = [
    'aws-dev' => 'https://dev.workbc.ca',
    'aws-dev-noc' => 'https://devnoc.workbc.ca',
    'aws-test' => 'https://test.workbc.ca',
    'aws-prod' => 'https://www.workbc.ca',
];
if (array_key_exists(getenv('PROJECT_ENVIRONMENT'), $base_urls)) {
    $base_url = $base_urls[getenv('PROJECT_ENVIRONMENT')];
    $config['simple_sitemap.settings']['base_url'] = $base_url;
}

$databases['default']['default'] = array (
    'database' => getenv('POSTGRES_DB'),
    'username' => getenv('POSTGRES_USER'),
    'password' => getenv('POSTGRES_PASSWORD'),
    'prefix' => '',
    'host' => getenv('POSTGRES_HOST'),
    'port' => getenv('POSTGRES_PORT'),
    'namespace' => 'Drupal\\Core\\Database\\Driver\\pgsql',
    'driver' => 'pgsql',
);
$databases['ssot']['default'] = array (
    'database' => getenv('POSTGRES_SSOT') ?? 'ssot',
    'username' => getenv('POSTGRES_ADM_USER'),
    'password' => getenv('POSTGRES_ADM_PWD'),
    'prefix' => '',
    'host' => getenv('POSTGRES_HOST'),
    'port' => getenv('POSTGRES_PORT'),
    'namespace' => 'Drupal\\Core\\Database\\Driver\\pgsql',
    'driver' => 'pgsql',
);
$settings['hash_salt'] = json_encode($databases);

$settings['file_private_path'] = '/app/private';

// Email sending via AWS SES.
$config['system.mail']['interface']['default'] = 'ses_mail';
$config['system.mail']['interface']['webform'] = 'ses_mail';

// Single Source of Truth (SSoT) configuration.
$config['workbc']['ssot_url'] = getenv('SSOT_URL');

$config['jobboard']['jobboard_api_url_frontend'] = getenv('JOBBOARD_API_URL');
$config['jobboard']['jobboard_api_url_backend'] = getenv('JOBBOARD_API_INTERNAL_URL');
$config['jobboard']['google_maps_key'] = getenv('JOBBOARD_GOOGLE_MAPS_KEY');

$settings['redis.connection']['host'] = getenv('REDIS_HOST');
$settings['redis.connection']['port'] = getenv('REDIS_PORT');

// Career Trek configuration
$config['workbc']['careertrek_url'] = getenv('CAREERTREK_URL');