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
 *   label = @Translation("Labour Market Info - Forecasted Employment Growth Rate"),
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_provincial'])) {
      $data = array();
      $data[] = floatval($entity->ssot_data['career_provincial']['forecasted_average_employment_growth_rate_first5y']);
      $data[] = floatval($entity->ssot_data['career_provincial']['forecasted_average_employment_growth_rate_second5y']);

      $date1 = ssotParseDateRange($entity->ssot_data['schema'], 'career_provincial', 'job_openings_first');
      $date2 = ssotParseDateRange($entity->ssot_data['schema'], 'career_provincial', 'job_openings_second');
      $date3 = ssotParseDateRange($entity->ssot_data['schema'], 'career_provincial', 'job_openings_third');
      $dates = array();
      $dates[] = $date1 . "-" . $date2;
      $dates[] = $date2 . "-" . $date3;

      $labels = $dates;
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
          '#max' => 5,
          '#min' => 0,
        ]
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
