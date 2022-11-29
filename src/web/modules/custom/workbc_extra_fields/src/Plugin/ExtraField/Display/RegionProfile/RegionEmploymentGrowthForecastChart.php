<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_employment_growth_rate_forecast_chart",
 *   label = @Translation("Employment Growth Rate Forecast Chart"),
 *   description = @Translation("An extra field to display employment growth forecast total."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionEmploymentGrowthForecastChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    $date1 = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_labour_market_outlook', 'forecasted_employment_growth_rate_first5y');
    $date2 = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_labour_market_outlook', 'forecasted_employment_growth_rate_second5y');
    $date1 = explode("-", $date1);
    $date2 = explode("-", $date2);
    $datestr = $date1[0] . " - " . $date2[1];
    return $this->t('Forecasted Employment Growth Rate (' . $datestr . ')');
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

    $date1 = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_labour_market_outlook', 'forecasted_employment_growth_rate_first5y');
    $date2 = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_labour_market_outlook', 'forecasted_employment_growth_rate_second5y');
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['regional_labour_market_outlook']['forecasted_employment_growth_rate_second5y'])) {
      $data = array();
      $data[] = $entity->ssot_data['regional_labour_market_outlook']['forecasted_employment_growth_rate_first5y'];
      $data[] = $entity->ssot_data['regional_labour_market_outlook']['forecasted_employment_growth_rate_second5y'];
      $labels = [$date1, $date2];
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'column',
        'series' => [
          '#type' => 'chart_data',
          '#title' => t(''),
          '#data' => $data,
          '#prefix' => '',
          '#suffix' => '',
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => $labels,
          '#max' => count($data),
          '#min' => 0,
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
          '#max' => max($data),
          '#min' => 0,
        ]
      ];
      $output = \Drupal::service('renderer')->render($chart);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
