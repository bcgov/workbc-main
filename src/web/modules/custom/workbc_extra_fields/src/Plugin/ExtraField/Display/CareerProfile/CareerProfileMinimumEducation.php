<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "minimum_education",
 *   label = @Translation("TEER"),
 *   description = @Translation("Training, Education, Experience and Responsibilities"),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileMinimumEducation extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Training, Education, Experience and Responsibilities');
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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['education']['teer'])) {
        $vocabulary = 'taxonomy_term';
        $terms = \Drupal::entityTypeManager()->getStorage($vocabulary)->loadByProperties([
            'vid' => 'education',
            'field_teer' => $entity->ssot_data['education']['teer'],
        ]);
        $term = $terms[array_key_first($terms)];
        if ($term) {
          $output = $term->getName();
        }
        else {
          $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
        }
      }
    else {
        $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
