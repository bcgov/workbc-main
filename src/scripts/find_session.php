<?php

/**
 * Retrieve session entry for given cookie.
 * https://drupal.stackexchange.com/a/231726/767
 *
 * Usage: drush scr scripts/find_session.php -- --cookie=<URL-decoded value of SESSxxxx cookie>
 */
use Drupal\Component\Utility\Crypt;

$getopt = new \GetOpt\GetOpt([
  ['c', 'cookie', \GetOpt\GetOpt::REQUIRED_ARGUMENT, 'Cookie to query for session'],
], []);
try {
  $getopt->process($extra);
}
catch (Exception $e) {
  die($getopt->getHelpText() . PHP_EOL);
}
$cookie = trim($getopt->getOption('cookie'));
$sid = Crypt::hashBase64($cookie);

function read($sid) {
  $data = '';
  if (!empty($sid)) {
    // Read the session data from the database.
    $connection = \Drupal::database();
    $query = $connection->queryRange('SELECT session FROM {sessions} WHERE sid = :sid', 0, 1, [':sid' => $sid]);
    $data = (string) $query->fetchField();
  }
  return $data;
}

echo "Cookie value: $cookie\n";
echo "Session sid: $sid\n";
echo "Session data: " . var_export(read($sid), TRUE) . "\n";
