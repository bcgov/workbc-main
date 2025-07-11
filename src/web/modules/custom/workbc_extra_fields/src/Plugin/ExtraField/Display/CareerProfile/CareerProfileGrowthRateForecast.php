<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "employment_growth_rate_forecast",
 *   label = @Translation("[SSOT] Labour Market Info - Forecasted Employment Growth Rate"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileGrowthRateForecast extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Forecasted Employment Growth Rate');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_provincial']) &&
        !is_null($entity->ssot_data['career_provincial']['forecasted_average_employment_growth_rate_first5y']) &&
        !is_null($entity->ssot_data['career_provincial']['forecasted_average_employment_growth_rate_second5y'])) {
      $data = array();
      $data[] = floatval($entity->ssot_data['career_provincial']['forecasted_average_employment_growth_rate_first5y']);
      $data[] = floatval($entity->ssot_data['career_provincial']['forecasted_average_employment_growth_rate_second5y']);
      $date1 = ssotParseDateRange($entity->ssot_data['schema'], 'career_provincial', 'forecasted_average_employment_growth_rate_first5y');
      $date2 = ssotParseDateRange($entity->ssot_data['schema'], 'career_provincial', 'forecasted_average_employment_growth_rate_second5y');
      $dates[] = $date1;
      $dates[] = $date2;

      $chart = [
        '#chart_id' => 'career-forecasted-employment-growth-rate',
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
              'suffix' => "%",
              'positive_sign' => TRUE,
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
      $output = '<div class="workbc-data-not-available-200">' . WORKBC_EXTRA_FIELDS_DATA_NOT_AVAILABLE . "</div>";
    }
    return [
      ['#markup' => $output],
    ];
  }

}
