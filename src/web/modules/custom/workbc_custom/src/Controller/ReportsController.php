<?php

/**
 * @file
 * CustomModalController class.
 */

namespace Drupal\workbc_custom\Controller;

use Drupal\Core\Controller\ControllerBase;

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
}
