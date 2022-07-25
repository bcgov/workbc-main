<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "high_opportunity_occupation",
 *   label = @Translation("High Opportunity Occupation"),
 *   description = @Translation("High Opportunity Occupation"),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileHighOpportunityOccupation extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('High Opportunity Occupation');
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

    if (!empty($entity->ssot_data) && !empty($entity->ssot_data['high_opportunity_occupations'])) {
        $output = 'YES';
    }
    else {
        $output = 'NO';
    }
    return [
      ['#markup' => $output],
    ];
  }

}
