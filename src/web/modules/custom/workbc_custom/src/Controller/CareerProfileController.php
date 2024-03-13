<?php

/**
 * @file
 * Custom Controller class.
 */

namespace Drupal\workbc_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CareerProfileController extends ControllerBase {


  public function career_profile_noc($noc) {

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['field_noc' => $noc]);
    $node = array_shift($nodes);

    if ($node) {
      $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()]);
      return new RedirectResponse($url->toString());
    }
    else {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

  }

}
