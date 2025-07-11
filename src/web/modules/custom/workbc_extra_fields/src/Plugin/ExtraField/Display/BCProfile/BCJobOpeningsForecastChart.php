<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_job_openings_forecast_chart",
 *   label = @Translation("[SSOT] Job Openings Forecast Chart"),
 *   description = @Translation("An extra field to display region job openings forecast chart."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCJobOpeningsForecastChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Forecasted Job Openings');
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
      $data = array();
      $data[] = round(floatval($entity->ssot_data['regional_labour_market_outlook']['job_openings_first5y']));
      $data[] = round(floatval($entity->ssot_data['regional_labour_market_outlook']['job_openings_second5y']));

      $date1 = ssotParseDateRange($entity->ssot_data['schema'], 'regional_labour_market_outlook', 'job_openings_first5y');
      $date2 = ssotParseDateRange($entity->ssot_data['schema'], 'regional_labour_market_outlook', 'job_openings_second5y');
      $dates = array();
      $dates[] = $date1;
      $dates[] = $date2;
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'column',
        '#colors' => ['#2E6AB0'],
        'series' => [
          '#type' => 'chart_data',
          '#data' => $data,
          '#title' => t('Forecasted Job Openings'),
        ],
        'series_annotation' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => $data,
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
