<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a WorkBC Explore Careers Search Block.
 *
 * @Block(
 *   id = "explore_careers_search_block",
 *   admin_label = @Translation("WorkBC Explore Careers Search block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class ExploreCareersSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm('Drupal\workbc_custom\Form\ExploreCareersSearchForm');
  }

}
