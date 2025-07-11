<?php

// phpcs:ignoreFile

/**
 * @file
 * Local development override configuration feature.
 *
 * To activate this feature, copy and rename it such that its path plus
 * filename is 'sites/default/settings.local.php'. Then, go to the bottom of
 * 'sites/default/settings.php' and uncomment the commented lines that mention
 * 'settings.local.php'.
 *
 * If you are using a site name in the path, such as 'sites/example.com', copy
 * this file to 'sites/example.com/settings.local.php', and uncomment the lines
 * at the bottom of 'sites/example.com/settings.php'.
 */

$base_url = sprintf('http://%s:%s', getenv('PROJECT_BASE_URL'), getenv('PROJECT_PORT'));
$config['simple_sitemap.settings']['base_url'] = $base_url;

$databases['default']['default'] = [
  'database' => $_SERVER['DB_NAME'],
  'driver' => $_SERVER['DB_DRIVER'],
  'host' => $_SERVER['DB_HOST'],
  'password' => $_SERVER['DB_PASSWORD'],
  'port' => $_SERVER['DB_PORT'],
  'prefix' => '',
  'username' => $_SERVER['DB_USER'],
];
$databases['ssot']['default'] = [
  'database' => 'ssot',
  'driver' => 'pgsql',
  'host' => 'postgres',
  'password' => 'workbc',
  'port' => 5432,
  'prefix' => '',
  'username' => 'workbc',
];

/**
 * Assertions.
 *
 * The Drupal project primarily uses runtime assertions to enforce the
 * expectations of the API by failing when incorrect calls are made by code
 * under development.
 *
 * @see http://php.net/assert
 * @see https://www.drupal.org/node/2492225
 *
 * If you are using PHP 7.0 it is strongly recommended that you set
 * zend.assertions=1 in the PHP.ini file (It cannot be changed from .htaccess
 * or runtime) on development machines and to 0 in production.
 *
 * @see https://wiki.php.net/rfc/expectations
 */
assert_options(ASSERT_ACTIVE, TRUE);
\Drupal\Component\Assertion\Handle::register();

/**
 * Control caching in the local development environment.
 */
const LOCAL_CACHE_ACTIVE = FALSE;

/**
 * Enable local development services.
 */
if (LOCAL_CACHE_ACTIVE) {
  $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.cache.yml';
}
else {
  $settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
}

/**
 * Show all error messages, with backtrace information.
 *
 * In case the error level could not be fetched from the database, as for
 * example the database connection failed, we rely only on this value.
 */
$config['system.logging']['error_level'] = 'verbose';

/**
 * Disable CSS and JS aggregation.
 */
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
$config['advagg.settings']['enabled'] = FALSE;

/**
 * Disable the render cache.
 *
 * Note: you should test with the render cache enabled, to ensure the correct
 * cacheability metadata is present. However, in the early stages of
 * development, you may want to disable it.
 *
 * This setting disables the render cache by using the Null cache back-end
 * defined by the development.services.yml file above.
 *
 * Only use this setting once the site has been installed.
 */
if (!LOCAL_CACHE_ACTIVE) {
  $settings['cache']['bins']['render'] = 'cache.backend.null';
}

/**
 * Disable caching for migrations.
 *
 * Uncomment the code below to only store migrations in memory and not in the
 * database. This makes it easier to develop custom migrations.
 */
# $settings['cache']['bins']['discovery_migration'] = 'cache.backend.memory';

/**
 * Disable Internal Page Cache.
 *
 * Note: you should test with Internal Page Cache enabled, to ensure the correct
 * cacheability metadata is present. However, in the early stages of
 * development, you may want to disable it.
 *
 * This setting disables the page cache by using the Null cache back-end
 * defined by the development.services.yml file above.
 *
 * Only use this setting once the site has been installed.
 */
if (!LOCAL_CACHE_ACTIVE) {
  $settings['cache']['bins']['page'] = 'cache.backend.null';
}

/**
 * Disable Dynamic Page Cache.
 *
 * Note: you should test with Dynamic Page Cache enabled, to ensure the correct
 * cacheability metadata is present (and hence the expected behavior). However,
 * in the early stages of development, you may want to disable it.
 */
if (!LOCAL_CACHE_ACTIVE) {
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
}

/**
 * Allow test modules and themes to be installed.
 *
 * Drupal ignores test modules and themes by default for performance reasons.
 * During development it can be useful to install test extensions for debugging
 * purposes.
 */
# $settings['extension_discovery_scan_tests'] = TRUE;

/**
 * Enable access to rebuild.php.
 *
 * This setting can be enabled to allow Drupal's php and database cached
 * storage to be cleared via the rebuild.php page. Access to this page can also
 * be gained by generating a query string from rebuild_token_calculator.sh and
 * using these parameters in a request to rebuild.php.
 */
# $settings['rebuild_access'] = TRUE;

/**
 * Skip file system permissions hardening.
 *
 * The system module will periodically check the permissions of your site's
 * site directory to ensure that it is not writable by the website user. For
 * sites that are managed with a version control system, this can cause problems
 * when files in that directory such as settings.php are updated, because the
 * user pulling in the changes won't have permissions to modify files in the
 * directory.
 */
$settings['skip_permissions_hardening'] = TRUE;

/**
 * Exclude modules from configuration synchronization.
 *
 * On config export sync, no config or dependent config of any excluded module
 * is exported. On config import sync, any config of any installed excluded
 * module is ignored. In the exported configuration, it will be as if the
 * excluded module had never been installed. When syncing configuration, if an
 * excluded module is already installed, it will not be uninstalled by the
 * configuration synchronization, and dependent configuration will remain
 * intact. This affects only configuration synchronization; single import and
 * export of configuration are not affected.
 *
 * Drupal does not validate or sanity check the list of excluded modules. For
 * instance, it is your own responsibility to never exclude required modules,
 * because it would mean that the exported configuration can not be imported
 * anymore.
 *
 * This is an advanced feature and using it means opting out of some of the
 * guarantees the configuration synchronization provides. It is not recommended
 * to use this feature with modules that affect Drupal in a major way such as
 * the language or field module.
 */
# $settings['config_exclude_modules'] = ['devel', 'stage_file_proxy'];

/**
 * WorkBC local development configuration.
 */
$settings['redis.connection']['host'] = 'redis';
$settings['redis.connection']['port'] = '6379';

$config['workbc']['ssot_url'] = 'http://ssot:3000';

$config['jobboard']['jobboard_api_url_frontend'] = 'https://workbc-jb.b89n0c-dev.nimbus.cloud.gov.bc.ca';
$config['jobboard']['jobboard_api_url_backend'] = 'https://workbc-jb.b89n0c-dev.nimbus.cloud.gov.bc.ca';
$config['jobboard']['google_maps_key'] = '';

$config['backup_migrate.backup_migrate_source.ssot_database'] = [
  'id' => 'ssot_database',
  'label' => 'SSoT Database',
  'type' => 'PostgreSQL',
  'config' => [
    'host' => 'postgres',
    'database' => 'ssot',
    'username' => 'workbc',
    'password' => 'workbc',
    'port' => 5432
  ]
];

$config['search_api.server.solr'] = [
  'id' => 'solr',
  'name' => 'Solr',
  'description' => '',
  'backend' => 'search_api_solr',
  'backend_config' => [
    'retrieve_data' => false,
    'highlight_data' => false,
    'site_hash' => false,
    'server_prefix' => '',
    'domain' => 'generic',
    'environment' => 'default',
    'connector' => 'standard',
    'connector_config' => [
      'scheme' => 'http',
      'host' => 'solr',
      'port' => 8983,
      'path' => '/',
      'core' => 'workbc_dev',
      'timeout' => 5,
      'index_timeout' => 5,
      'optimize_timeout' => 10,
      'finalize_timeout' => 30,
      'skip_schema_check' => false,
      'solr_version' => '',
      'http_method' => 'AUTO',
      'commit_within' => 1000,
      'jmx' => false,
      'jts' => false,
      'solr_install_dir' => '',
    ],
    'optimize' => false,
    'fallback_multiple' => false,
    'disabled_field_types' => [],
    'disabled_caches' => [],
    'disabled_request_handlers' => [
      'request_handler_elevate_default_7_0_0',
      'request_handler_replicationmaster_default_7_0_0',
      'request_handler_replicationslave_default_7_0_0',
    ],
    'disabled_request_dispatchers' => [
      'request_dispatcher_httpcaching_default_7_0_0',
    ],
    'rows' => 10,
    'index_single_documents_fallback_count' => 10,
    'index_empty_text_fields' => false,
    'suppress_missing_languages' => false
  ],
];
$config['search_api.server.solr_search'] = [
  'id' => 'solr_search',
//  'status' => false,
  'name' => 'Solr (Career Trek)',
  'description' => '',
  'backend' => 'search_api_solr',
  'backend_config' => [
    'retrieve_data' => false,
    'highlight_data' => false,
    'site_hash' => false,
    'server_prefix' => '',
    'domain' => 'generic',
    'environment' => 'default',
    'connector' => 'standard',
    'connector_config' => [
      'scheme' => 'http',
      'host' => 'solr',
      'port' => 8983,
      'path' => '/',
      'core' => 'workbc_dev',
      'timeout' => 5,
      'index_timeout' => 5,
      'optimize_timeout' => 10,
      'finalize_timeout' => 30,
      'skip_schema_check' => false,
      'solr_version' => '',
      'http_method' => 'AUTO',
      'commit_within' => 1000,
      'jmx' => false,
      'jts' => false,
      'solr_install_dir' => '',
    ],
    'optimize' => false,
    'fallback_multiple' => false,
    'disabled_field_types' => [],
    'disabled_caches' => [],
    'disabled_request_handlers' => [
      'request_handler_elevate_default_7_0_0',
      'request_handler_replicationmaster_default_7_0_0',
      'request_handler_replicationslave_default_7_0_0',
    ],
    'disabled_request_dispatchers' => [
      'request_dispatcher_httpcaching_default_7_0_0',
    ],
    'rows' => 10,
    'index_single_documents_fallback_count' => 10,
    'index_empty_text_fields' => false,
    'suppress_missing_languages' => false
  ],
];
//$config['search_api.index.career_profile_index_sub']['status'] = false;

// Enable/disable features
$config['workbc']['features']['ssot_upload'] = TRUE;

ini_set('memory_limit', '1G');
if (class_exists('Kint')) {
  // Set the max_depth to prevent out-of-memory.
  \Kint::$depth_limit = 3;
}
