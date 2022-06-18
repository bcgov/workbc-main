<?php

namespace Drupal\workbc_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a WorkBC Menu Block.
 *
 * @Block(
 *   id = "menu_block",
 *   admin_label = @Translation("WorkBC menu block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class MenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('Hello, WorkBC Menu!'),
    ];
  }
}
