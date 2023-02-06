<?php

use Drush\Commands\DrushCommands;

/**
 * @file
 * Reset module hook_post_update_NAME
 *
 * A tool for developing and debugging post_update hooks.
 *
 * This script will ONLY reset the hook to be run again ...
 *
 * If data is being changed, backup the database and restore as needed
 *
 * Usage:
 * drush php:script reset_hook_post_update.php [-- PREFIX]
 *
 * @see https://www.drush.org/latest/commands/php_script/
 *
 * https://gist.github.com/bryanbraun/852646078ef6b33d2dc2ecacc96c9865
 * https://www.drush.org/latest/api/Drush/Commands/DrushCommands.html
 */

$key_value = \Drupal::keyValue('post_update');
$update_list = $key_value->get('existing_updates');

$indexes = array_flip($update_list);
$choices = array_reverse($update_list);
if ($extra) {
    // The `$extra` variable is added by Drush when running php:script
    $prefix = array_shift($extra);
    $choices = array_filter(
        $choices,
        fn($c) => str_starts_with($c, $prefix)
    );
}

$io = DrushCommands::io();
$reset = $io->choice("Which post_update hook do you want to reset?", $choices, 0);
if ($reset !== FALSE) {
    $remove = $choices[$reset];
    $index = $indexes[$remove];
    if ($remove === $update_list[$index]) {
        unset($update_list[$index]);
        $key_value->set("existing_updates", $update_list);
        $io->success("$remove has been reset.");
    }
    else {
        // Really should not even get here!
        $io->error("Unable to match selection!");
    }
}
// Cancelled message is handled in `choice`.