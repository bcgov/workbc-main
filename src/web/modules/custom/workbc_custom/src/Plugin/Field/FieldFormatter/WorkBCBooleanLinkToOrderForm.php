<?php

namespace Drupal\workbc_custom\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Plugin implementation of the 'workbc_file_custom_link_text' formatter.
 *
 * @FieldFormatter(
 *   id = "workbc_boolean_link_to_order_form",
 *   label = @Translation("Link to order form"),
 *   field_types = {
 *     "boolean"
 *   }
 * )
 */
class WorkBCBooleanLinkToOrderForm extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $value = $item->get('value')->getCastedValue();
      if ($value) {
        $options = [];
        $result = Link::fromTextAndUrl(t('Order Hardcopy'), Url::fromUri('internal:/workbc-order-form', $options))->toString();
        $elements[$delta] = ['#markup' => $result];
      }
    }
    return $elements;
  }

}
