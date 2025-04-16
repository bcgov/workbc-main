<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\views\Views;

/**
 * Provides a WorkBC Related topics Block.
 *
 * @Block(
 *   id = "career_exploration_block",
 *   admin_label = @Translation("WorkBC Career Exploration block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class CareerExplorationBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    // load Parent terms
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('epbc_categories', 0, 1, TRUE);
    $categories = [];
    $interests = [];
    foreach ($terms as $term) {
      $categories[$term->id()] = $term->getName();
      $interests[$term->id()] = [];
    }

    // load Areas of Interest grouped by Parent
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('epbc_categories', 0, 2, TRUE);
    foreach ($terms as $term) {
      if ($term->depth > 0) {
        $interests[$term->parent->target_id][$term->id()] = $term->getName();

      }
    }

    $career_exploration = array(
      'categories' => $categories,
      'interests' => $interests,
    );

    $renderable = [
      '#theme' => 'career_exploration_block',
      '#career_exploration' => $career_exploration,
    ];

    return $renderable;
  }

}
