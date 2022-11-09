<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_population",
 *   label = @Translation("BC Population"),
 *   description = @Translation("An extra field to display BC population."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCPopulation extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $date1 = strtotime($this->getEntity()->ssot_data['monthly_labour_market_updates']['year'] . "-" . $this->getEntity()->ssot_data['monthly_labour_market_updates']['month']. "-01", 10);
    return $this->t("Population - age 15+ (" . date("M Y", $date1) . ")");
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['monthly_labour_market_updates'])) {
      $field = 'population_' . $entity->ssot_data['region'];
      $output = ssotFormatNumber($entity->ssot_data['monthly_labour_market_updates'][$field],0);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
