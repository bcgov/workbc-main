<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_10y_chart",
 *   label = @Translation("[SSOT] Job Openings, B.C."),
 *   description = @Translation("An extra field to display job openings chart."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class BCJobOpeningsCompositionChart extends LabourMarketOutLookExtraFieldBase {

  protected function viewDatasetElement() {
    $data = [
      floatval(array_find($this->report->ssot_data[$this->getDataset()], function($entry) {
        return $entry['key'] === 'expansion';
      })['amount']),
      floatval(array_find($this->report->ssot_data[$this->getDataset()], function($entry) {
        return $entry['key'] === 'replacement';
      })['amount'])
    ];
    $chart = [
      '#chart_id' => 'lmo_report_job_openings_10y_chart',
      '#type' => 'chart',
      '#chart_type' => 'donut',
      '#colors' => array(
        '#009cde',
        '#002857'
      ),
      'series' => [
        '#type' => 'chart_data',
        '#title' => $this->t('Composition of Job Openings'),
        '#data' => $data,
      ],
      'xaxis' => [
        '#type' => 'chart_xaxis',
        '#labels' => [$this->t('Expansion'), $this->t('Replacement')],
      ],
      'yaxis' => [
        '#type' => 'chart_yaxis',
      ],
      '#legend_position' => 'right',
      '#raw_options' => [
        'options' => [
          'chartArea' => [
            'left' => '10%',
            'top' => '10%',
            'width' => '80%',
            'height' => '80%',
          ],
          'pieHole' => 0.7,
          'height' => 300,
          'pieSliceText' => 'none',
        ]
      ]
    ];
    $output = \Drupal::service('renderer')->render($chart);
    $source_text = $this->report->ssot_data['sources']['label'] ?? WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $output .= <<<END
    <div class="lm-source"><strong>Source:</strong>&nbsp;$source_text</div>
    END;
    return $output;
  }

}
