<?php

use Drush\Commands\DrushCommands;
use Drupal\redirect\Entity\Redirect;

/**
 * @file
 * Dump and delete redirections
 *
 * A tool to export redirects to CSV and optionally delete them.
 *
 * Usage:
 * drush php:script reset_redirects.php [-- -d|--delete]
 */

// Parse arguments.
$options = [
  'delete' => false,
  'output' => 'redirects.csv'
];
if ($extra) {
  foreach ($extra as $arg) {
    $opt = strtolower($arg);
    if (in_array($opt, [
      '-d', '--delete'
    ])) {
      $options['delete'] = true;
    }
  }
}

// Query the redirects.
$database = \Drupal::database();
$query = $database->query("
SELECT rid, redirect_source__path, redirect_redirect__uri FROM {redirect} r
WHERE r.redirect_source__path ILIKE '%.aspx'
OR r.redirect_source__path IN (
  SELECT replace(r2.redirect_source__path, '.aspx', '') FROM {redirect} r2 WHERE r2.redirect_source__path ILIKE '%.aspx'
)
ORDER BY redirect_source__path ASC
");
$redirects = $query->fetchAll();

// Produce the CSV.
$langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
$output = fopen($options['output'], 'w');
$count = 0;
fputcsv($output, ['FROM', 'TO']);
foreach($redirects as $redirect) {
  // Get the redirection alias.
  $target = $redirect->redirect_redirect__uri;
  if (preg_match('/node\/(\d+)/', $target, $matches)) {
    $target = \Drupal::service('path_alias.manager')->getaliasByPath("/node/{$matches[1]}");
  }
  else if (str_starts_with($target, 'internal:')) {
    $target = str_replace('internal:', '', $target);
  }
  else if (str_starts_with($target, 'entity:')) {
    $target = str_replace('entity:', '/', $target);
  }
  fputcsv($output, [$redirect->redirect_source__path, $target]);
  $count++;
}
fwrite(STDERR, "Exported {$count} redirections to {$options['output']}." . PHP_EOL);

// Delete the redirects if needed.
$io = DrushCommands::io();
if ($options['delete'] && $io->confirm("Are you sure you want to delete the redirects?", false)) {
  $count = 0;
  array_walk($redirects, function($redirect) use(&$count) {
    $entity = Redirect::load($redirect->rid);
    if ($entity) {
      $entity->delete();
      $count++;
    }
  });
  fwrite(STDERR, "Deleted {$count} redirections." . PHP_EOL);
}
else {
  fwrite(STDERR, "Skipped deleting redirections." . PHP_EOL);
}
