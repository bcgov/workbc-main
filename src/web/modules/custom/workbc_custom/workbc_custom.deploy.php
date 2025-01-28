<?php

use Drupal\redirect\Entity\Redirect;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\MediaStorage;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Assign Exclude from Video Page value - Remote Video.
 *
 * As per ticket WBCAMS-809.
 */
function workbc_custom_deploy_809_media_exclude(&$sandbox = NULL) {
  if (!isset($sandbox['videos'])) {
    // load remote videos
    $database = \Drupal::database();

    $query = $database->select('media_field_data', 'm');
    $query->condition('m.bundle', 'remote_video', '=');
    $query->fields('m', ['mid', 'name', 'status', 'created']);
    $sandbox['videos'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['videos']);
  }

  $message = "No action taken.";
  $video = array_shift($sandbox['videos']);

  $media = Media::load($video->mid);
  if ($media) {
    if (is_null($media->field_exclude_from_video_page->value)) {
      $media->field_exclude_from_video_page = false;
      $media->save();
      $message = "Remote Video: " . $media->name->value . " - exclude set to false";
    }
    else {
      $message = "Remote Video: " . $media->name->value . " - exclude already set";
    }
  }
  else {
    $message = $search . " - Media " . $record->mid . " not found";
  }


  $sandbox['#finished'] = empty($sandbox['videos']) ? 1 : ($sandbox['count'] - count($sandbox['videos'])) / $sandbox['count'];
  return t("[WBCAMS-809] $message");
}
