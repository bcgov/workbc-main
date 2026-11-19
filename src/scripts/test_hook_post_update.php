<?php

/**
 * Simulate running a deploy hook for testing purposes.
 *
 * Usage:
 * drush php:script test_hook_post_update.php -- FUNCTION_NAME
 */

if (empty($extra)) {
  die("Hook function name missing. Aborting." . PHP_EOL);
}
$hook = $extra[0];
if (str_contains($hook, '_deploy_')) {
  $module = explode('_deploy_', $hook)[0];
  $suffix = 'deploy';
}
else if (str_contains($hook, '_post_update_')) {
  $module = explode('_post_update_', $hook)[0];
  $suffix = 'post_update';
}
else {
  die("Hook function name unrecognized: {$hook}. Aborting." . PHP_EOL);
}
$module_path = \Drupal::service('extension.path.resolver')->getPath('module', $module);
$file = "{$module_path}/{$module}.{$suffix}.php";
include_once($file);
if (!function_exists($hook) && !function_exists("test_{$hook}")) {
  die("Hook function name not found: {$hook}. Aborting." . PHP_EOL);
}

$sandbox = ['#finished' => 0];
while ($sandbox['#finished'] !== 1) {
  if (function_exists($hook)) {
    $message = $hook($sandbox);
  }
  else {
    $message = "test_{$hook}"($sandbox);
  }
  fwrite(STDOUT, $message . PHP_EOL);
}
