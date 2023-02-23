<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_growth_rate_one_year_forecast",
 *   label = @Translation("Employment Growth Annual Forecast"),
 *   description = @Translation("An extra field to display industry employment growth annual forecast."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentGrowthRateOneYearForecast extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Forecasted Average Annual Employment Growth Rate');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['industry_outlook'])) {
      $data = array();
      $data[] = $entity->ssot_data['industry_outlook']['annual_employment_growth_rate_pct_first5y'];
      $data[] = $entity->ssot_data['industry_outlook']['annual_employment_growth_rate_pct_second5y'];
      $date1 = ssotParseDateRange($entity->ssot_data['schema'], 'industry_outlook', 'annual_employment_growth_rate_pct_first5y');
      $date2 = ssotParseDateRange($entity->ssot_data['schema'], 'industry_outlook', 'annual_employment_growth_rate_pct_second5y');
      $dates = array();
      $dates[] = $date1;
      $dates[] = $date2;
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'column',
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Forecasted Average Annual Employment Growth Rate'),
          '#data' => $data,
        ],
        'series_annotation' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => array_map(function($v) {
            return ssotFormatNumber($v, 1, true) . '%';
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
      $output = "";
    }

    return [
      ['#markup' => $output],
    ];
  }

}
