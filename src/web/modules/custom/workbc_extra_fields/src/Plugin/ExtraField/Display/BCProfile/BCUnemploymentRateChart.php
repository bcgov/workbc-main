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
 *   label = @Translation("[SSOT] 10 Year Unemployment Rate Chart"),
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

    return $this->t('10-year Unemployment Rate in B.C.');
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
      $year = intval(ssotParseDateRange($entity->ssot_data['schema'], 'labour_force_survey_regional_employment', 'total_employment_num'));
      $hi = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_high']);
      $lo = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_low']);
      $bc = array();
      $years = array();
      for ($i = 1; $i <= 11; $i++) {
        $bc[] = floatval($entity->ssot_data['labour_force_survey_bc_employment']['unemployment_rate_year_'.$i]);
        $years[] = $year - 11 + $i;
      }
      $chart = [
        '#chart_id' => 'bc-unemployment-rate',
        '#type' => 'chart',
        '#chart_type' => 'line',
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('British Columbia'),
          '#data' => $bc,
          '#color' => '#002857',
          '#suffix' => '%',
        ],
        'series_tooltip' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip'],
          '#data' => array_map(function($v, $i) use($years) {
            return $v . '% in ' . $years[$i];
          }, $bc, array_keys($bc)),
          '#suffix' => '%',
        ],
        'series_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => array_map(function($v) use($hi, $lo) {
            if (abs($v - $hi) < PHP_FLOAT_EPSILON) {
              return 'point { size: 5; shape-type: circle; stroke-width: 2; stroke-color: #ee0000; fill-color: #fff }';
            }
            else if (abs($v - $lo) < PHP_FLOAT_EPSILON) {
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
          '#max' => min(100, $hi + 5),
        ],
        '#legend_position' => 'none',
        '#data_markers' => TRUE,
      ];
      $output = \Drupal::service('renderer')->render($chart);

      // Render the legends
      $output .= <<<EOS
      <div class='card-profile__legend-container'>
        <div class='card-profile__legend'>
          <div class='card-profile__legend-title'>British Columbia</div>

          <div class='card-profile__legend-item card-profile__legend-item--low'>
            <div class='card-profile__legend-label card-profile__legend-label--low'>Low:</div>
            <div class='card-profile__legend-value card-profile__default-color'>$lo%</div>
          </div>

          <div class='card-profile__legend-item card-profile__legend-item--high'>
            <div class='card-profile__legend-label card-profile__legend-label--high'>High:</div>
            <div class='card-profile__legend-value card-profile__default-color'>$hi%</div>
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
