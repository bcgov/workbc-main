<?php

/**
 * @file
 * Post update functions for workBC Career Trek.
 */

use Drupal\system\Entity\Action;

/**
 * Install the 'Set and Reset featured video' actions.
 */
function workbc_career_trek_post_update_install_set_reset_featured_action_2() {
  if (!Action::load('media_set_featured')) {
    Action::create([
      'id' => 'media_set_featured',
      'label' => 'Set featured video',
      'type' => 'media',
      'plugin' => 'media_set_featured',
    ])
      ->save();
  }
  if (!Action::load('media_reset_featured')) {
    Action::create([
      'id' => 'media_reset_featured',
      'label' => 'Reset featured video',
      'type' => 'media',
      'plugin' => 'media_reset_featured',
    ])
      ->save();
  }
}
