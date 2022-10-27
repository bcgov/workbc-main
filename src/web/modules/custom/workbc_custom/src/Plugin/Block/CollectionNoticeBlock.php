<?php
/**
 * @file
 * Contains \Drupal\workbc_custom\Plugin\Block\ModalBlock.
 */

namespace Drupal\workbc_custom\Plugin\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Component\Serialization\Json;

/**
 * Provides a 'Modal' Block
 *
 * @Block(
 *   id = "collection_notice_block",
 *   admin_label = @Translation("Collection Notice block"),
 * )
 */
class CollectionNoticeBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $link_url = Url::fromRoute('workbc_custom.collection_notice');
    $link_url->setOptions([
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => 300]),
      ]
    ]);

    return array(
      '#type' => 'markup',
      '#markup' => Link::fromTextAndUrl(t('View Collection Notice'), $link_url)->toString() . " >",
      '#attached' => ['library' => ['core/drupal.dialog.ajax']]
    );
  }
}
