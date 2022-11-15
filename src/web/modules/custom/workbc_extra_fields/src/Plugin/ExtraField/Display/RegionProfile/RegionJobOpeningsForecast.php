<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_job_openings_forecast",
 *   label = @Translation("Job Openings Forecast"),
 *   description = @Translation("An extra field to display region job openings forecast."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionJobOpeningsForecast extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $datestr1 = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_labour_market_outlook', 'employment_outlook_first');
    $datestr2 = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_labour_market_outlook', 'employment_outlook_third');
    return $this->t("Total Forecasted Job Openings (" . $datestr1 . "-" . $datestr2 . ")");
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['regional_labour_market_outlook'])) {
      $output = ssotFormatNumber($entity->ssot_data['regional_labour_market_outlook']['forecasted_total_employment_growth_10y'],0);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
