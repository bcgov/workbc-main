<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_industries_chart",
 *   label = @Translation("Figure 3-1: Top Ten Major Industry Groups by Job Openings, B.C., 2024-2034"),
 *   description = @Translation("An extra field to display job openings chart."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class JobOpeningsIndustryGroupsChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    $label = $this->getEntity()->getParentEntity()->ssot_data['schema']['definitions']['lmo_report_2024_job_openings_industries']['description'];
    return trim(explode('>', $label)[1]);
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
  public function viewElements(ContentEntityInterface $paragraph) {

    // Don't display if this field is not selected in the parent paragraph.
    if ($this->getPluginId() != $paragraph->get('field_lmo_charts_tables')->value) {
      return null;
    }

    $entity = $paragraph->getParentEntity();
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['lmo_report_2024_job_openings_industries'])) {
      $colorReplacement = '#002857';
      $colorExpansion = '#009cde';

      $data = $entity->ssot_data['lmo_report_2024_job_openings_industries'];
      foreach ($data as $category) {
        if (in_array($category['industry'], ['top_3', 'top_5'])) continue;

        $replacement = round($category['replacement']);
        $expansion = round($category['expansion']);
        $label = $category['name'];
        $regions[] = $label;
        $series2[] = $replacement;
        $styles2[] = "stroke-color: $colorReplacement; stroke-width: 1;";
        $annotations2[] = "$replacement\u{00a0}";
        $tooltips2[] = "<div style=\"margin:10px\"><strong>$label</strong><br><span style=\"white-space:nowrap\">Replacement: <strong>$replacement</strong></span></div>";
        $series1[] = $expansion;
        $annotations1[] = "$expansion\u{00a0}";
        $tooltips1[] = "<div style=\"margin:10px\"><strong>$label</strong><br><span style=\"white-space:nowrap\">Expansion: <strong>$expansion</strong></span></div>";
        $styles1[] = "stroke-color: $colorExpansion; stroke-width: 1;";
      }

      // Stacked column chart with two series.
      $chart = [
        '#chart_id' => 'lmo_report_2024_job_openings_industries_chart',
        '#type' => 'chart',
        '#chart_type' => 'bar',
        '#colors' => array(
          $colorExpansion,
          $colorReplacement,
        ),
        'series_one' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Expansion'),
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
          '#title' => $this->t('Replacement'),
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
        '#width' => 100, '#width_units' => '',
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
