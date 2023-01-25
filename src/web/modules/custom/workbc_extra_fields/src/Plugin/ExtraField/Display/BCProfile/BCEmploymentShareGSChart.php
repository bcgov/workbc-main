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
      $regions = [];
      $series1 = [];
      $series2 = [];

      foreach ($entity->ssot_data['labour_force_survey_regional_industry_province'] as $region) {
        $goods = round($region['goods']);
        $services = round($region['services']);
        if ($region['region'] <> "british_columbia") {
          $regions[] = ssotRegionName($region['region']);
          $series1[] = $goods;
          $annotations1[] = $goods . "%";
          $series2[] = $services;
          $annotations2[] = $services . "%";
        }
        else {
          $bcGoods = $goods;
          $bcServices = $services;
        }
      }

      $regions[] = $this->t('B.C. Average');
      $series1[] = $bcGoods;
      $annotations1[] = $bcGoods . "%";
      $series2[] = $bcServices;
      $annotations2[] = $bcServices . "%";

      $xaxis = [
        '#type' => 'chart_xaxis',
        '#labels' => $regions,
      ];

      $yaxis = [
        '#type' => 'chart_yaxis',
      ];

      // Stacked column chart with two series.
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'bar',
        '#colors' => array(
          '#002857',
          '#009cde'
        ),
        'series_one' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Goods'),
          '#data' => $series1,
        ],
        'series_one_annotations' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => $annotations1,
        ],
        'series_two' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Services'),
          '#data' => $series2,
        ],
        'series_two_annotations' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => $annotations2,
        ],
        'x_axis' => $xaxis,
        'y_axis' => $yaxis,
        '#stacking' => TRUE,
        '#height' => 500, '#height_units' => 'px',
        '#width' => 100, '#width_units' => '%',
        '#legend_position' => 'bottom',
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
