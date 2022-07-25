<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\Publication;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "publication_orderform_link",
 *   label = @Translation("Order Form Link"),
 *   description = @Translation("An extra field to display a link to the WorkBC Order form if Hardcopy available is checked."),
 *   bundles = {
 *     "node.publication",
 *   }
 * )
 */
class PublicationOrderFormLink extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return "";
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay() {

    return 'above';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {


    if ($entity->hasField('field_hardcopy_available')) {
      $hardcopy = $entity->get("field_hardcopy_available")->value;
      if ($hardcopy) {
        $options = [];
        $result = Link::fromTextAndUrl(t('Order Hardcopy'), Url::fromUri('internal:/workbc-order-form', $options))->toString();
        $output = $result;
      }
      else {
        $output = "";
      }

    }
    else {
      $output = "";
    }

    return [
      ['#markup' => $output],
    ];
  }

}
