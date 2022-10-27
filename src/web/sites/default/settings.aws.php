<?php

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

$settings['hash_salt'] = json_encode($databases);

$settings['file_private_path'] = '/app/private';

// Email sending via AWS SES.
$config['system.mail']['interface']['default'] = 'ses_mail';
$config['system.mail']['interface']['webform'] = 'ses_mail';

// Single Source of Truth (SSoT) configuration.
$config['workbc']['ssot_url'] = getenv('SSOT_URL');

$config['jobboard']['jobboard_api_url'] = 'https://test-api-jobboard.workbc.ca';
$config['jobboard']['find_job_url'] = '/search-and-prepare-job/find-jobs';
$config['jobboard']['find_job_account_url'] = '/account';

$config['jobboard']['search_career_profile_link'] = '/jobs-careers/explore-careers';
$config['jobboard']['labour_market_outlook'] = '/labour-market-industry/labour-market-outlook';
$config['jobboard']['explore_industryand_sector_outlooks'] = '/labour-market-industry/industry-sector-outlooks';
$config['jobboard']['view_industry_profiles'] = '/labour-market-industry/industry-profiles';