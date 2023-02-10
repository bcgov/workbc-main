<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_growth_rate_ten_year_forecast",
 *   label = @Translation("Employment Growth 10 Year Forecast"),
 *   description = @Translation("An extra field to display industry employment growth 10 year forecast."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentGrowthRateTenYearForecast extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Forecasted 10-Year Industry Share of Total Employment');
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
      $data[] = $entity->ssot_data['industry_outlook']['share_total_employment_pct_first'];
      $data[] = $entity->ssot_data['industry_outlook']['share_total_employment_pct_second'];
      $data[] = $entity->ssot_data['industry_outlook']['share_total_employment_pct_third'];
      $date1 = ssotParseDateRange($entity->ssot_data['schema'], 'industry_outlook', 'share_total_employment_pct_first');
      $date2 = ssotParseDateRange($entity->ssot_data['schema'], 'industry_outlook', 'share_total_employment_pct_second');
      $date3 = ssotParseDateRange($entity->ssot_data['schema'], 'industry_outlook', 'share_total_employment_pct_third');
      $dates = array();
      $dates[] = $date1;
      $dates[] = $date2;
      $dates[] = $date3;
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'column',
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Forecasted 10-Year Industry Share of Total Employment'),
          '#data' => $data,
        ],
        'series_annotation' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => array_map(function($v) {
            return ssotFormatNumber($v, 1) . '%';
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
