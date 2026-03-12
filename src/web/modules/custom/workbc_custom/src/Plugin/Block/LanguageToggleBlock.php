<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a WorkBC Language toggle block.
 *
 * @Block(
 *   id = "language_toggle_block",
 *   admin_label = @Translation("Language toggle block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class LanguageToggleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // It's empty because the template will take care of everything.
    return ['#markup' => ''];
  }
}
