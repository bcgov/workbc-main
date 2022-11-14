<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_employment_shart_goods_services_chart",
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
      $regions = [];
      $series1 = [];
      $series2 = [];

      foreach ($entity->ssot_data['labour_force_survey_regional_industry_province'] as $region) {
        if ($region['region'] <> "british_columbia") {
          $regions[] = ssotRegionName($region['region']);
          $series1[] = $region['goods'];
          $styles1[] = '';
          $annotations1[] = $region['goods'] . "%";
          $series2[] = $region['services'];
          $styles2[] = '';
          $annotations2[] = $region['services'] . "%";
        }
        else {
          $bcGoods = $region['goods'];
          $bcServices = $region['services'];
        }
      }
      $regions[] = "";
      $series1[] = 0;
      $annotations1[] = "";
      $series2[] = 0;
      $annotations2[] = "";
      $regions[] = ssotRegionName('british_columbia');
      $series1[] = $bcGoods;
      $annotations1[] = $bcGoods . "%";
      $series2[] = $bcServices;
      $annotations2[] = $bcServices . "%";

      // Define an x-axis to be used in multiple examples.
      $xaxis = [
        '#type' => 'chart_xaxis',
        '#labels' => $regions,
      ];

      // Define a y-axis to be used in multiple examples.
      $yaxis = [
        '#type' => 'chart_yaxis',
      ];
      // Stacked column chart with two series.
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'bar',
        '#colors' => array(
          '#002857',
          '#009cde'),
        'series_one' => [
          '#type' => 'chart_data',
          '#title' => t('Goods'),
          '#data' => $series1,
        ],
        'series_one_annotations' => [
          '#type' => 'chart_data',
          '#title' => [ 'role' => 'annotation'],
          '#data' => $annotations1,
        ],
        'series_two' => [
          '#type' => 'chart_data',
          '#title' => t('Services'),
          '#data' => $series2,
        ],
        'series_two_annotations' => [
          '#type' => 'chart_data',
          '#title' => [ 'role' => 'annotation'],
          '#data' => $annotations2,
        ],
        'x_axis' => $xaxis,
        'y_axis' => $yaxis,
        '#stacking' => TRUE,
        '#raw_options' => [
          'options' => [
            'height' => '500',
            'legend' => [
              'position' => 'bottom',
            ],
          ],
        ],
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
