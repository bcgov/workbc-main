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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['regional_labour_market_outlook']['forecasted_employment_growth_rate_second5y'])) {
      $data = array();
      $data[] = $entity->ssot_data['regional_labour_market_outlook']['forecasted_employment_growth_rate_first5y'];
      $data[] = $entity->ssot_data['regional_labour_market_outlook']['forecasted_employment_growth_rate_second5y'];
      $date1 = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_labour_market_outlook', 'forecasted_employment_growth_rate_first5y');
      $date2 = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_labour_market_outlook', 'forecasted_employment_growth_rate_second5y');
      $dates = array();
      $dates[] = $date1;
      $dates[] = $date2;
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'column',
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Forecasted Employment Growth Rate'),
          '#data' => $data,
        ],
        'series_annotation' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => array_map(function($v) {
            $options = array(
              'decimals' => 1,
              'positive_sign' => TRUE,
              'suffix' => "%",
              'na_if_empty' => TRUE,
            );
            return ssotFormatNumber($v, $options);
          }, $data),
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => $dates,
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
          '#raw_options' => [
            'textPosition' => 'none',
            'gridlines' => [
              'count' => 1,
            ],
            'minValue' => 0,
          ]
        ],
        '#legend_position' => 'none',
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
