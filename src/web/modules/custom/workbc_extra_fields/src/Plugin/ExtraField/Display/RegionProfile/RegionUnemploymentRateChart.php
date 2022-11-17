<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_unemployment_rate_chart",
 *   label = @Translation("10 Year Unemployment Rate Chart"),
 *   description = @Translation("An extra field to display unemployment rate."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionUnemploymentRateChart extends ExtraFieldDisplayFormattedBase {

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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_regional_employment'])) {
      $region = array();
      $bc = array();
      for ($i = 1; $i <= 11; $i++) {
        $region[] = floatval($entity->ssot_data['labour_force_survey_regional_employment']['unemployment_rate_year_'.$i]);
        $bc[] = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_'.$i]);
      }
      $regionHi = floatval($entity->ssot_data['labour_force_survey_regional_employment']['unemployment_rate_year_high']);
      $regionLo = floatval($entity->ssot_data['labour_force_survey_regional_employment']['unemployment_rate_year_low']);
      $bcHi = floatval($entity->ssot_data['labour_force_survey_regional_employment']['unemployment_rate_year_high']);
      $bcLo =  floatval($entity->ssot_data['labour_force_survey_regional_employment']['unemployment_rate_year_low']);
      $max = max(array_merge($region, $bc));
      $min = min(array_merge($region, $bc));
      $labels = [t('Region'), t('BC')];
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'line',
        'series_one' => [
          '#type' => 'chart_data',
          '#title' => t('Region'),
          '#data' => $region,
          '#prefix' => '',
          '#suffix' => '',
        ],
        'series_two' => [
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
