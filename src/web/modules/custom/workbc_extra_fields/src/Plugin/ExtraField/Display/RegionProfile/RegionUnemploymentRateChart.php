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

    return $this->t('10-year Unemployment Rate');
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
      $regionName = ssotRegionName($entity->ssot_data['labour_force_survey_regional_employment']['region']);
      $year = intval(ssotParseDateRange($entity->ssot_data['schema'], 'labour_force_survey_regional_employment', 'total_employment_num'));
      $regionHi = floatval($entity->ssot_data['labour_force_survey_regional_employment']['unemployment_rate_year_high']);
      $regionLo = floatval($entity->ssot_data['labour_force_survey_regional_employment']['unemployment_rate_year_low']);
      $bcHi = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_high']);
      $bcLo = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_low']);
      $region = array();
      $bc = array();
      $years = array();
      for ($i = 1; $i <= 11; $i++) {
        $region[] = floatval($entity->ssot_data['labour_force_survey_regional_employment']['unemployment_rate_year_'.$i]);
        $bc[] = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_'.$i]);
        $years[] = $year - 11 + $i;
      }
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'line',
        'series_region' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Region'),
          '#data' => $region,
          '#color' => '#a6a6a6',
          '#suffix' => '%',
        ],
        'series_region_tooltip' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip'],
          '#data' => array_map(function($v, $i) use($years) {
            return $v . '% in ' . $years[$i];
          }, $region, array_keys($region)),
          '#suffix' => '%',
        ],
        'series_region_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => array_map(function($v) use($regionHi, $regionLo) {
            if (abs($v - $regionHi) < PHP_FLOAT_EPSILON) {
              return 'point { size: 5; shape-type: circle; stroke-width: 2; stroke-color: #ee0000; fill-color: #fff }';
            }
            else if (abs($v - $regionLo) < PHP_FLOAT_EPSILON) {
              return 'point { size: 5; shape-type: circle; stroke-width: 2; stroke-color: #008e2d; fill-color: #fff }';
            }
            return 'point { size: 4; shape-type: circle }';
          }, $region),
          '#suffix' => '%',
        ],
        'series_bc' => [
          '#type' => 'chart_data',
          '#title' => $this->t('British Columbia'),
          '#data' => $bc,
          '#color' => '#002857',
          '#suffix' => '%',
        ],
        'series_bc_tooltip' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip'],
          '#data' => array_map(function($v, $i) use($years) {
            return $v . '% in ' . $years[$i];
          }, $bc, array_keys($bc)),
          '#suffix' => '%',
        ],
        'series_bc_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => array_map(function($v) use($bcHi, $bcLo) {
            if (abs($v - $bcHi) < PHP_FLOAT_EPSILON) {
              return 'point { size: 5; shape-type: circle; stroke-width: 2; stroke-color: #ee0000; fill-color: #fff }';
            }
            else if (abs($v - $bcLo) < PHP_FLOAT_EPSILON) {
              return 'point { size: 5; shape-type: circle; stroke-width: 2; stroke-color: #008e2d; fill-color: #fff }';
            }
            return 'point { size: 4; shape-type: circle }';
          }, $bc),
          '#suffix' => '%',
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => $years,
          '#raw_options' => [
            'format' => '',
          ]
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
          '#min' => 0,
          '#max' => min(100, max($bcHi, $regionHi) + 5),
        ],
        '#legend_position' => 'none',
        '#data_markers' => TRUE,
        '#raw_options' => [
          'options' => [
          ],
        ]
      ];
      $output = \Drupal::service('renderer')->render($chart);

      // Render the legends
      $output .= <<<EOS
      <div class='card-profile__legends-container'>
        <div class='card-profile__legend'>
          <div class='card-profile__legend-title'>$regionName</div>

          <div class='card-profile__legend-item card-profile__legend-item--low'>
            <div class='card-profile__legend-label card-profile__legend-label--low'>Low:</div>
            <div class='card-profile__legend-value'>$regionLo%</div>
          </div>

          <div class='card-profile__legend-item card-profile__legend-item--high'>
            <div class='card-profile__legend-label card-profile__legend-label--high'>High:</div>
            <div class='card-profile__legend-value'>$regionHi%</div>
          </div>
        </div>

        <div class='card-profile__legend'>
          <div class='card-profile__legend-title card-profile__legend-title--grey'>British Columbia</div>

          <div class='card-profile__legend-item card-profile__legend-item--low'>
            <div class='card-profile__legend-label card-profile__legend-label--low'>Low:</div>
            <div class='card-profile__legend-value'>$bcLo%</div>
          </div>

          <div class='card-profile__legend-item card-profile__legend-item--high'>
            <div class='card-profile__legend-label card-profile__legend-label--high'>High:</div>
            <div class='card-profile__legend-value'>$bcHi%</div>
          </div>
        </div>
      </div>
EOS;
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
