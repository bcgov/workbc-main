<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "occupational_categories",
 *   label = @Translation("Occupational Categories"),
 *   description = @Translation("An extra field to display career occupational categories."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileOccupationalCategories extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Occupational Categories');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['occupational_category'][0]['category'])) {
      $output = $entity->ssot_data['occupational_category'][0]['category'];
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
