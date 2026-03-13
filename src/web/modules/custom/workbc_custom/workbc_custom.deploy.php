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


/**
 * Fix mismatch entity type.
 *
 * As per ticket WBCAMS-1204
 */
function workbc_custom_deploy_1204_mismatch_fix(&$sandbox = NULL) {

  \Drupal::service("meaofd.fixer")->fix("media");
  return t("[WBCAMS-1204] fix The Drupal Media entity type issues.");
}


/**
 * Fix mismatch entity type.
 *
 * As per ticket WBCAMS-1717
 */
function workbc_custom_deploy_1717_import_centre_regions(&$sandbox = NULL) {

  if (!isset($sandbox['centres'])) {
    // load remote videos
    $database = \Drupal::database();

    $module_path = \Drupal::service('extension.path.resolver')->getPath('module', 'workbc_custom');
    $file_path = $module_path . '/data/centres_regions.csv';
    if (file_exists($file_path)) {
      if (($handle = fopen($file_path, 'r')) !== FALSE) {
        $data = [];
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
          $data[] = $row;
        }
        fclose($handle);
      }
    }
    $sandbox['centres'] = $data;
    $sandbox['count'] = count($sandbox['centres']);
  }

  $message = "No action taken.";
  $centre = array_shift($sandbox['centres']);

  $entity_type_manager = \Drupal::entityTypeManager();

  // Load all nodes matching the title
  $nodes = $entity_type_manager->getStorage('node')->loadByProperties(['title' => $centre[0]]);
  if (!empty($nodes)) {
    foreach ($nodes as $node) {
      // You can then access its properties, e.g.,
      $node->set('field_region', $centre[1]);
      $node->save();
    }
    $message = "WorkBC Centre: " . $centre[0] . " - region set to " . $centre[1];
  }

  $sandbox['#finished'] = empty($sandbox['centres']) ? 1 : ($sandbox['count'] - count($sandbox['centres'])) / $sandbox['count'];
  return t("[WBCAMS-1717] $message");
}
