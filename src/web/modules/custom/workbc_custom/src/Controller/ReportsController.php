<?php

/**
 * @file
 * CustomModalController class.
 */

namespace Drupal\workbc_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

class ReportsController extends ControllerBase {
  public function files() {
    $files = getUnmanagedFiles();
    return [[
      '#markup' => 'This is a report of pages containing unmanaged files instead of media library items.<br>
      Click the <b>Edit</b> link to edit the page, then look for the field named in the <b>Field</b> column to find the content to be edited.<br>
      Click the <b>Source</b> button of the editor to locate the unmanaged file(s) in the field content.
      It will typically be an HTML tag that references a file like <code>/sites/default/files/filename.pdf</code>.'
    ],[
      '#theme' => 'table',
      '#header' => ['Page', 'Field', 'Edit'],
      '#rows' => array_map(function ($file) {
        return [
          $file['title'],
          $file['label'],
          Link::fromTextAndUrl($this->t('Edit'), $file['edit_url'])
        ];
      }, $files),
    ]];
  }

  public function environment() {
    ob_start();
    phpinfo((INFO_VARIABLES | INFO_ENVIRONMENT));
    $env = ob_get_clean();
    return [
      '#type' => 'markup',
      '#markup' => $env,
    ];
  }
}
