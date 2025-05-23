<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_population",
 *   label = @Translation("Population"),
 *   description = @Translation("An extra field to display region population."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionPopulation extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    $datestr = empty($this->getEntity()->ssot_data) ? '' : strtotime($this->getEntity()->ssot_data['monthly_labour_market_updates']['year'] . "-" . $this->getEntity()->ssot_data['monthly_labour_market_updates']['month']. "-01", 10);
    return array('#markup' => $this->t("Population - age 15+") . "<br>(" . date("M Y", $datestr) . ")");
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

    $options = array(
      'decimals' => 0,
      'na_if_empty' => TRUE,
    );
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['monthly_labour_market_updates'])) {
      $field = 'population_' . $entity->ssot_data['region'];
      $output = ssotFormatNumber($entity->ssot_data['monthly_labour_market_updates'][$field], $options);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
