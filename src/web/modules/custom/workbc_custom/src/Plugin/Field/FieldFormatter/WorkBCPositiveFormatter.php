<?php

namespace Drupal\workbc_custom\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'workbc_positive' formatter.
 *
 * @FieldFormatter(
 *   id = "workbc_positive",
 *   label = @Translation("Non-negative"),
 *   field_types = {
 *     "integer",
 *     "numeric",
 *   }
 * )
 */
class WorkBCPositiveFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $options = array(
      'no_negative' => true,
      'na_if_empty' => true,
    );
    foreach ($items as $delta => $item) {
      $result = ssotFormatNumber($item->value, $options);
      $elements[$delta] = ['#markup' => $result];
    }
    return $elements;
  }
}
