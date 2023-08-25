<?php

declare(strict_types = 1);

/**
 * @file
 * Post update functions for the Ckeditor Media Resize module.
 */

use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\FileStorage;

/**
 * Import config for dynamic resizing of images using image styles.
 */
function ckeditor_media_resize_post_update_image_style_config_import(&$sandbox) {
  $path = \Drupal::service('extension.list.module')->getPath('ckeditor_media_resize');
  $path .= '/' . InstallStorage::CONFIG_INSTALL_DIRECTORY;
  $source = new FileStorage($path);
  /** @var \SplFileInfo $file */
  foreach (new \DirectoryIterator($path) as $file) {
    if ($file->isFile()) {
      /** @var \Drupal\Core\Config\StorageInterface $active_storage */
      $active_storage = \Drupal::service('config.storage');
      $config_name = $file->getBasename('.yml');
      $active_storage->write($config_name, $source->read($config_name));
    }
  }
}
