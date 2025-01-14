<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_teers_chart",
 *   label = @Translation("Job Openings by TEER, B.C. (2024-2034)"),
 *   description = @Translation("An extra field to display job openings chart."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class JobOpeningsTeersChart extends ExtraFieldDisplayFormattedBase {

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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['lmo_report_2024_job_openings_teers'])) {

      // Donut chart for desktop.
      $data = array_map(function($entry) {
        return $entry['openings_rounded'];
      }, array_filter($entity->ssot_data['lmo_report_2024_job_openings_teers'], function ($entry) {
        return $entry['teer'] !== 'Total';
      }));
      $chart = [
        '#chart_id' => 'lmo_report_2024_job_openings_teers_chart',
        '#type' => 'chart',
        '#chart_type' => 'donut',
        '#colors' => array(
          '#70ad47',
          '#5b9bd5',
          '#ffc000',
          '#43682b',
          '#255e91',
          '#997300'
        ),
        'series' => [
          '#type' => 'chart_data',
          '#data' => $data,
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => array_map(function($entry) {
            return $entry['teer'];
          }, array_filter($entity->ssot_data['lmo_report_2024_job_openings_teers'], function ($entry) {
            return $entry['teer'] !== 'Total';
          })),
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
        ],
        '#legend_position' => 'right',
        '#raw_options' => [
          'options' => [
            'chartArea' => [
              'left' => '15%',
              'top' => '15%',
              'width' => '70%',
              'height' => '70%',
            ],
            'pieHole' => 0.7,
            'height' => 400,
            'pieSliceText' => 'none',
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
      $options2 = array(
        'decimals' => 1,
        'suffix' => '%',
        'na_if_empty' => TRUE,
      );

      $output .= <<<END
      <table class="lmo-mobile lmo-report">
        <thead>
          <tr>
            <th class="data-align-left">Training, Education, Experience and Responsibilities</th>
            <th class="data-align-right">Job Openings</th>
            <th class="data-align-right">% of Total</th>
          </tr>
        </thead>
        <tbody>
      END;
      foreach ($entity->ssot_data['lmo_report_2024_job_openings_teers'] as $entry) {
        if ($entry['teer'] === 'Total') continue;
        $output .= '<tr>';
        $output .= '<td class="data-align-left lmo-report-teer" data-label="Training, Education, Experience and Responsibilities">' . $entry['teer'] . '</td>';
        $output .= '<td class="data-align-right lmo-report-openings" data-label="Job Openings">' . ssotFormatNumber($entry['openings_rounded'], $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-percentage" data-label="% of Total">' . ssotFormatNumber(100 * $entry['fraction'], $options2) . '</td>';
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
