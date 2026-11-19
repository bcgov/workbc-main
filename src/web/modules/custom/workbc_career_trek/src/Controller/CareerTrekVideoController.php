<?php

/**
 * @file
 * CustomController class.
 */

namespace Drupal\workbc_career_trek\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CareerTrekVideoController extends ControllerBase {

  public function content($noc, $episode) {
    $entityStorage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $entityStorage->getQuery();
    $query->condition('type', 'career_profile');
    $query->condition('field_noc', $noc, '=');
    $query->condition('status', 1);
    $result = $query->accessCheck(false)->execute();
    if (!$result) throw new NotFoundHttpException();
    $node = Node::Load(reset($result));
    $node->episode_number = $episode;
    $mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
    $query = $mediaStorage->getQuery();
    $query->condition('bundle', 'remote_video');
    $query->condition('field_episode', $episode, '=');
    $query->condition('status', 1);
    $result = $query->accessCheck(false)->execute();
    if (!$result) throw new NotFoundHttpException();
    $node->mid = reset($result);
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    return $view_builder->view($node, 'full_career_trek_videos');
  }

  public function getTitle($noc, $episode) {
    $mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
    $query = $mediaStorage->getQuery();
    $query->condition('bundle', 'remote_video');
    $query->condition('field_episode', $episode, '=');
    $query->condition('status', 1);
    $result = $query->accessCheck(false)->execute();
    if (!$result) throw new NotFoundHttpException();
    $video = Media::load(reset($result));
    return $video->getName();
  }
}
