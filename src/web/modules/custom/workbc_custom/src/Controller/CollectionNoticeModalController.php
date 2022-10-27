<?php

/**
 * @file
 * CustomModalController class.
 */

namespace Drupal\workbc_custom\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;

class CollectionNoticeModalController extends ControllerBase {

  public function modal() {
    $options = [
      'dialogClass' => 'popup-dialog-class',
      'width' => '50%',
    ];

    $config = $this->config('workbc_custom.settings');

    $text = $config->get('collectionsettings.notice')['value'];
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand(t('Collection Notice Terms'), $text, $options));

    return $response;
  }
}
