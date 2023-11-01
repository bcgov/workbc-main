<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_employment_goods_service_share_chart",
 *   label = @Translation("Employment Share Goods & Services Chart"),
 *   description = @Translation("An extra field to display employment share goods & services chart."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCEmploymentShareGSChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_regional_industry_province', 'goods');
    return $this->t('Share of Employment in Goods and Services Sector (' . $datestr . ')');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_regional_industry_province'])) {
      $colorGoods = '#002857';
      $colorServices = '#009cde';

      $ssot = $entity->ssot_data['labour_force_survey_regional_industry_province'];
      usort($ssot, function ($r1, $r2) {
        if ($r1['region'] === 'british_columbia') return 1;
        if ($r2['region'] === 'british_columbia') return -1;
        return strcasecmp($r1['region'], $r2['region']);
      });
      foreach ($ssot as $region) {
        $goods = round($region['goods']);
        $services = round($region['services']);
        $label = $region['region'] === 'british_columbia' ? 'B.C. Average' : ssotRegionName($region['region']);
        $regions[] = $label;
        $series1[] = $goods;
        $styles1[] = "stroke-color: $colorGoods; stroke-width: 1;";
        $annotations1[] = "$goods%\u{00a0}";
        $tooltips1[] = "<div style=\"margin:10px\"><strong>$label</strong><br><span style=\"white-space:nowrap\">Goods: <strong>$goods%</strong></span></div>";
        $series2[] = $services;
        $annotations2[] = "$services%\u{00a0}";
        $tooltips2[] = "<div style=\"margin:10px\"><strong>$label</strong><br><span style=\"white-space:nowrap\">Services: <strong>$services%</strong></span></div>";
        $styles2[] = "stroke-color: $colorServices; stroke-width: 1;";
      }

      // Stacked column chart with two series.
      $chart = [
        '#chart_id' => 'bc-share-of-employment',
        '#type' => 'chart',
        '#chart_type' => 'bar',
        '#colors' => array(
          $colorGoods,
          $colorServices
        ),
        'series_one' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Goods'),
          '#data' => array_slice($series1, 0, -1),
        ],
        'series_one_annotations' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => array_slice($annotations1, 0, -1),
        ],
        'series_one_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => array_slice($styles1, 0, -1),
        ],
        'series_one_tooltips' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip', 'p' => ['html' => TRUE]],
          '#data' => array_slice($tooltips1, 0, -1),
        ],
        'series_two' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Services'),
          '#data' => array_slice($series2, 0, -1),
        ],
        'series_two_annotations' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => array_slice($annotations2, 0, -1),
        ],
        'series_two_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => array_slice($styles2, 0, -1),
        ],
        'series_two_tooltips' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip', 'p' => ['html' => TRUE]],
          '#data' => array_slice($tooltips2, 0, -1),
        ],
        'x_axis' => [
          '#type' => 'chart_xaxis',
          '#labels' => array_slice($regions, 0, -1),
        ],
        'y_axis' => [
          '#type' => 'chart_yaxis',
        ],
        '#stacking' => TRUE,
        '#height' => 400, '#height_units' => 'px',
        '#width' => 100, '#width_units' => '%',
        '#legend_position' => 'none',
        '#raw_options' => [
          'options' => [
            'tooltip' => [
              'isHtml' => TRUE,
            ],
            'annotations' => [
              'textStyle' => [
              ]
            ],
            'bar' => [
              'groupWidth' => '75%'
            ],
          ]
        ]
      ];

      $chart_avg = [
        '#chart_id' => 'bc-share-of-employment-avg',
        '#type' => 'chart',
        '#chart_type' => 'bar',
        '#colors' => array(
          $colorGoods,
          $colorServices
        ),
        'series_one' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Goods'),
          '#data' => array_slice($series1, -1),
        ],
        'series_one_annotations' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => array_slice($annotations1, -1),
        ],
        'series_one_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => array_slice($styles1, -1),
        ],
        'series_one_tooltips' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip', 'p' => ['html' => TRUE]],
          '#data' => array_slice($tooltips1, -1),
        ],
        'series_two' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Services'),
          '#data' => array_slice($series2, -1),
        ],
        'series_two_annotations' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => array_slice($annotations2, -1),
        ],
        'series_two_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => array_slice($styles2, -1),
        ],
        'series_two_tooltips' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip', 'p' => ['html' => TRUE]],
          '#data' => array_slice($tooltips2, -1),
        ],
        'x_axis' => [
          '#type' => 'chart_xaxis',
          '#labels' => array_slice($regions, -1),
          '#labels_font_weight' => 'bold',
        ],
        'y_axis' => [
          '#type' => 'chart_yaxis',
        ],
        '#stacking' => TRUE,
        '#legend_position' => 'bottom',
        '#raw_options' => [
          'options' => [
            'tooltip' => [
              'isHtml' => TRUE,
            ],
            'annotations' => [
              'textStyle' => [
                'bold' => TRUE,
              ]
            ],
            'bar' => [
              'groupWidth' => '75%'
            ],
          ]
        ]
      ];

      $output = \Drupal::service('renderer')->render($chart) . \Drupal::service('renderer')->render($chart_avg);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
