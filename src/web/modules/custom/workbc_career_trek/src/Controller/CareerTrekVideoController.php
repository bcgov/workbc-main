<?php

/**
 * @file
 * CustomController class.
 */

namespace Drupal\workbc_career_trek\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;

class CareerTrekVideoController extends ControllerBase {

  public function content($noc, $episode) {
    $entityStorage = \Drupal::entityTypeManager()->getStorage('node');
    $query = $entityStorage->getQuery();
    $query->condition('type', 'career_profile');
    $query->condition('field_noc', $noc, '=');
    $query->condition('status', 1);
    $result = $query->accessCheck(false)->execute();
    $node = Node::load($result[array_key_first($result)]);

    $mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
    $query = $mediaStorage->getQuery();
    $query->condition('bundle', 'remote_video');
    $query->condition('field_episode', $episode, '=');
    $query->condition('status', 1);
    $result = $query->accessCheck(false)->execute();
    $video = Media::load($result[array_key_first($result)]);

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $render_array = $view_builder->view($node, 'full_career_trek_videos');
    \Drupal::service('renderer')->renderRoot($render_array);

    $content = [
      '#theme' => 'career_trek_video_detail',
      '#noc' => $noc,
      '#episode' => $episode,
      '#node' => $node,
      '#video' => $video,
      '#content' => $render_array,
      '#view_mode' => 'full-career-trek-videos',
    ];

    return $content;
  }
}
