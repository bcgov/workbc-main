<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\media\Entity\Media;
use Drupal\Core\Url;
use Drupal\Core\Link;
/**
 * Provides a WorkBC Related topics Block.
 *
 * @Block(
 *   id = "switch_language_block",
 *   admin_label = @Translation("WorkBC switch language block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class SwitchLanguageBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $renderable = array();

    $node = \Drupal::routeMatch()->getParameter('node');

    if (!$node->get('field_multilingual_centre')->isEmpty()) {
      $target = $node->field_multilingual_centre->getValue()[0];
      $options = [];
      $link = Link::fromTextAndUrl($target['title'], Url::fromUri($target['uri'], $options))->toString();

      $renderable = array(
        '#markup' => $link,
      );
    }
    else {
      $renderable = array();
    }
    return $renderable;

  }

  // public function getCacheMaxAge() {
  //     return 0;
  // }

  public function getCacheTags() {
    // With this when your node change your block will rebuild.
    if ($node = \Drupal::routeMatch()->getParameter('node')) {
      // If there is node add its cachetag.
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      // Return default tags instead.
      return parent::getCacheTags();
    }
  }

  public function getCacheContexts() {
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }


  private function renderLink($node) {
    $options = ['absolute' => TRUE];
    $link =$url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->id()], $options);
    return $link->toString();
  }

}
