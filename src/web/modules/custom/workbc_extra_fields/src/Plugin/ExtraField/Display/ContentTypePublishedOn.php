<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Provides a published on date field for use with content type manage display.
 * used the published_on_date format
 *
 * @ExtraFieldDisplay(
 *   id = "workbc_published_on",
 *   label = @Translation("Published On"),
 *   description = @Translation("An extra field to display published on date."),
 *   bundles = {
 *     "node.*",
 *   }
 * )
 */
class ContentTypePublishedOn extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return "Published On";
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay() {

    return 'hidden';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {

      $output = \Drupal::service('date.formatter')->format($entity->published_date->value, 'published_on_date');

    return [
      ['#markup' => $output],
    ];
  }

}
