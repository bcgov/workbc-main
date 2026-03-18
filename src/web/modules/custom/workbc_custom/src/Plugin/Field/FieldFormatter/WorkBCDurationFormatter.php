<?php

namespace Drupal\workbc_custom\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'workbc_video_duration' formatter.
 *
 * @FieldFormatter(
 *   id = "workbc_video_duration",
 *   label = @Translation("Video duration"),
 *   field_types = {
 *     "duration"
 *   }
 * )
 */
class WorkBCDurationFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $duration = $item->get('duration')->getCastedValue();
      $seconds = $item->get('seconds')->getCastedValue();
      if ($seconds >= 3600) {
        $value = $duration->format('%h:%I:%S');
      }
      else {
        $value = $duration->format('%i:%S');
      }
      $elements[$delta] = ['#markup' => $value];
    }
    return $elements;
  }

}
