<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_unemployment_rate_bc",
 *   label = @Translation("BC Unemployment Rate"),
 *   description = @Translation("An extra field to display BC unemployment rate."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionUnemploymentRateBC extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $datestr = empty($this->getEntity()->ssot_data) ? '' : ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_regional_employment', 'unemployment_rate_year_11');
    return $this->t('Unemployment Rate for all of B.C. (' . $datestr . ')');
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
      'decimals' => 1,
      'suffix' => "%",
      'na_if_empty' => TRUE,
    );
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_11'])) {
      $output = ssotFormatNumber($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_11'], $options);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
