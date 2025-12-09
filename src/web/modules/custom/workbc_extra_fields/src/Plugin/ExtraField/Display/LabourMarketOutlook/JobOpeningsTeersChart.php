<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_teers_chart",
 *   label = @Translation("[SSOT] Job Openings by TEER, B.C."),
 *   description = @Translation("An extra field to display job openings chart."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class JobOpeningsTeersChart extends LabourMarketOutLookExtraFieldBase {

  public function viewDatasetElement() {
    // Donut chart for desktop.
    $data = array_map(function($entry) {
      return $entry['openings_rounded'];
    }, array_filter($this->report->ssot_data[$this->getDataset()], function ($entry) {
      return $entry['teer'] !== 'Total';
    }));
    $chart = [
      '#chart_id' => 'lmo_report_job_openings_teers_chart',
      '#type' => 'chart',
      '#chart_type' => 'donut',
      '#colors' => array(
        '#002a59',
        '#81b216',
        '#720d8e',
        '#d95700',
        '#880364',
        '#216c06'
      ),
      'series' => [
        '#type' => 'chart_data',
        '#data' => $data,
      ],
      'xaxis' => [
        '#type' => 'chart_xaxis',
        '#labels' => array_map(function($entry) {
          return $entry['teer'];
        }, array_filter($this->report->ssot_data[$this->getDataset()], function ($entry) {
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
    foreach ($this->report->ssot_data[$this->getDataset()] as $entry) {
      if ($entry['teer'] === 'Total') continue;
      $output .= '<tr>';
      $output .= '<td class="data-align-left lmo-mobile">Training, Education, Experience and Responsibilities</td>';
      $output .= '<td class="data-align-left lmo-report-teer" data-label="Training, Education, Experience and Responsibilities">' . $entry['teer'] . '</td>';
      $output .= '<td class="data-align-right lmo-report-teer-openings" data-label="Job Openings">' . ssotFormatNumber($entry['openings_rounded'], $options1) . '</td>';
      $output .= '<td class="data-align-right lmo-report-percentage" data-label="% of Total">' . ssotFormatNumber(100 * $entry['fraction'], $options2) . '</td>';
      $output .= '</tr>';
    }
    $output .= '</tbody></table>';

    $source_text = $this->report->ssot_data['sources']['label'] ?? WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $output .= <<<END
    <div class="lm-source"><strong>Source:</strong>&nbsp;$source_text</div>
    END;
    return $output;
  }

}
