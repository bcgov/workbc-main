<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_broad_categories_chart",
 *   label = @Translation("Job Openings by Main Occupational Group, B.C. (2024-2034)"),
 *   description = @Translation("An extra field to display job openings chart."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class JobOpeningsOccupationGroupsChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['lmo_report_2024_job_openings_broad_categories'])) {

      // Bar chart for desktop.
      $options1 = array(
        'decimals' => 0,
        'na_if_empty' => TRUE,
      );

      $colorReplacement = '#002857';
      $colorExpansion = '#009cde';

      $data = $entity->ssot_data['lmo_report_2024_job_openings_broad_categories'];
      foreach ($data as $category) {
        if (!is_numeric($category['category'])) continue;

        $replacement = ssotFormatNumber($category['replacement'], $options1);
        $expansion = ssotFormatNumber($category['expansion'], $options1);
        $label = $category['name'];
        $labels[] = $label;
        $series2[] = $category['replacement'];
        $styles2[] = "stroke-color: $colorReplacement; stroke-width: 1;";
        $annotations2[] = "$expansion / $replacement";
        $tooltips2[] = "<div style=\"margin:10px\"><strong>$label</strong><br><span style=\"white-space:nowrap\">Replacement: <strong>$replacement</strong></span></div>";
        $series1[] = $category['expansion'];
        $tooltips1[] = "<div style=\"margin:10px\"><strong>$label</strong><br><span style=\"white-space:nowrap\">Expansion: <strong>$expansion</strong></span></div>";
        $styles1[] = "stroke-color: $colorExpansion; stroke-width: 1;";
      }

      // Stacked column chart with two series.
      $chart = [
        '#chart_id' => 'lmo_report_2024_job_openings_broad_categories_chart',
        '#type' => 'chart',
        '#chart_type' => 'bar',
        '#colors' => array(
          $colorExpansion,
          $colorReplacement,
        ),
        'series_one' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Expansion'),
          '#data' => $series1,
        ],
        'series_one_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => $styles1,
        ],
        'series_one_tooltips' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip', 'p' => ['html' => TRUE]],
          '#data' => $tooltips1,
        ],
        'series_two' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Replacement'),
          '#data' => $series2,
        ],
        'series_two_annotations' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => $annotations2,
        ],
        'series_two_style' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'style'],
          '#data' => $styles2,
        ],
        'series_two_tooltips' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip', 'p' => ['html' => TRUE]],
          '#data' => $tooltips2,
        ],
        'x_axis' => [
          '#type' => 'chart_xaxis',
          '#labels' => $labels,
        ],
        'y_axis' => [
          '#type' => 'chart_yaxis',
        ],
        '#stacking' => TRUE,
        '#height' => 700, '#height_units' => 'px',
        '#width' => 100, '#width_units' => '%',
        '#legend_position' => 'bottom',
        '#raw_options' => [
          'options' => [
            'chartArea' => [
              'left' => 200,
              'top' => 50,
              'width' => '60%',
              'height' => '85%',
            ],
            'height' => 700,
            'tooltip' => [
              'isHtml' => TRUE,
            ],
            'annotations' => [
              'alwaysOutside' => TRUE,
              'stem' => ['color' => 'transparent'],
              'textStyle' => ['color' => 'black'],
            ],
            'bar' => [
              'groupWidth' => '35'
            ],
          ]
        ]
      ];
      $output = '<div class="lmo-desktop">';
      $output .= \Drupal::service('renderer')->render($chart);
      $output .= '</div>';

      // Table for mobile.
      $options1 = array(
        'decimals' => 0,
        'na_if_empty' => TRUE,
      );

      $output .= <<<END
      <table class="lmo-mobile lmo-report">
        <thead>
          <tr>
            <th class="data-align-left">Occupational Group</th>
            <th class="data-align-right">Expansion</th>
            <th class="data-align-right">Replacement</th>
            <th class="data-align-right">Total Job Openings</th>
          </tr>
        </thead>
        <tbody>
      END;
      foreach ($entity->ssot_data['lmo_report_2024_job_openings_broad_categories'] as $entry) {
        if (!is_numeric($entry['category'])) continue;

        $replacement = round($entry['replacement']);
        $expansion = round($entry['expansion']);
        $openings = round($entry['openings']);

        $output .= '<tr>';
        $output .= '<td class="data-align-left lmo-mobile">Occupational Group</td>';
        $output .= '<td class="data-align-left lmo-report-occupation-group" data-label="Occupational Group">' . $entry['name'] . '</td>';
        $output .= '<td class="data-align-right lmo-report-expansion" data-label="Expansion">' . ssotFormatNumber($expansion, $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-replacement" data-label="Replacement">' . ssotFormatNumber($replacement, $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-openings" data-label="Total Job Openings">' . ssotFormatNumber($openings, $options1) . '</td>';
        $output .= '</tr>';
      }
      $output .= '</tbody></table>';

      $source_text = $entity->ssot_data['sources']['label'] ?? WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $output .= <<<END
      <div class="lm-source"><strong>Source:</strong>&nbsp;$source_text</div>
      END;
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
