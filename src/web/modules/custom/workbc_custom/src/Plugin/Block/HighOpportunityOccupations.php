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
 *   id = "high_opportunity_occupations",
 *   admin_label = @Translation("WorkBC high opportunity occupations  block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class HighOpportunityOccupations extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $form = \Drupal::formBuilder()->getForm('Drupal\workbc_custom\Form\HooOptionsForm');
    return $form;
  }

  /**
   * @return int
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
