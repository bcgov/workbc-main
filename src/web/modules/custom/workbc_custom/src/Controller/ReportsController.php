<?php

/**
 * @file
 * CustomModalController class.
 */

namespace Drupal\workbc_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\Entity\Media;

class ReportsController extends ControllerBase {
  public function environment() {
    ob_start();
    phpinfo((INFO_VARIABLES | INFO_ENVIRONMENT));
    $env = ob_get_clean();
    return [
      '#type' => 'markup',
      '#markup' => $env,
    ];
  }


  public function wbcams_1997() {
    $content = "";

    $database = \Drupal::database();

    $query = $database->select('media_field_data', 'm');
    $query->fields('m', ['mid', 'name', 'status', 'created', 'bundle']);
    $or_group = $query->orConditionGroup()
      ->condition('m.bundle', 'image', '=')
      ->condition('m.bundle', 'icon', '=');
    $query->condition($or_group);
    $images = $query->execute()->fetchAll();
    $total = count($images);

    $list = [];
    foreach ($images as $image) {
      $media = Media::load($image->mid);
      if ($image->bundle == "icon") {
        $image_value = $media->get('field_media_image_1')->getValue();
      }
      else {
        $image_value = $media->get('field_media_image')->getValue();
      }
      if (!empty($image_value) && strtolower($image_value[0]['alt']) == "alt") {
        $list[] = $image;
      }
    }

    $content .= "<p>Total Images: " . $total . "</p>";
    $content .= "<p>images/icons with Alternative text 'alt': " . count($list) . "</p>";
    $content .= "<br>";
    foreach ($list as $alt) {
      $content .= '<p><a href="/media/' . $alt->mid . '/edit">[' . $alt->mid . '] - ' . $alt->name . '</a></p>';
    }

    return [
      '#type' => 'markup',
      '#markup' => $content,
    ];
  }

}
