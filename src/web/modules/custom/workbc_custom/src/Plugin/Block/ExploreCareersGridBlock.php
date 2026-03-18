<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a WorkBC Explore Careers Grid (FYP Clone) Block.
 *
 * @Block(
 *   id = "explore_careers_grid_block",
 *   admin_label = @Translation("WorkBC Explore Careers Grid block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class ExploreCareersGridBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\workbc_custom\Form\ExploreCareersGridForm');
  }

}
