<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_unemployment_rate_chart",
 *   label = @Translation("10 Year Unemployment Rate Chart"),
 *   description = @Translation("An extra field to display unemployment rate."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCUnemploymentRateChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Unemployment Rate Chart');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_bc_employment'])) {
      $region = array();
      $bc = array();
      for ($i = 1; $i <= 11; $i++) {
        $bc[] = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_'.$i]);
      }
      $bcHi = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_high']);
      $bcLo =  floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_low']);
      $max = max($bc);
      $min = min($bc);
      $labels = [t('BC')];
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'line',
        'series' => [
          '#type' => 'chart_data',
          '#title' => t('BC'),
          '#data' => $bc,
          '#prefix' => '',
          '#suffix' => '',
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => $labels,
          '#max' => count($region),
          '#min' => 0,
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
          '#max' => $max+2,
          '#min' => $min-2,
        ]
      ];
      $output = \Drupal::service('renderer')->render($chart);
      // $output = "";
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
