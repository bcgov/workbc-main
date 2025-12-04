<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_regions_table",
 *   label = @Translation("[SSOT] Employment and Job Openings by Development Region, B.C."),
 *   description = @Translation("An extra field to display job openings regional table."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class BCJobOpeningsRegionTable extends LabourMarketOutLookExtraFieldBase {

  protected function viewDatasetElement() {
    $options1 = array(
      'decimals' => 0,
      'na_if_empty' => TRUE,
    );
    $options2 = array(
      'decimals' => 1,
      'suffix' => '%',
      'na_if_empty' => TRUE,
    );

    $employment_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'employment');
    $openings_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'openings');
    $expansion_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'expansion');
    $replacement_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'replacement');
    $growth_rate_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'growth_rate');

    $output = <<<END
    <table class="lmo-report">
      <thead>
        <tr>
          <th width="20%" rowspan="2" class="data-align-left">Region</th>
          <th rowspan="2" class="data-align-right">Employment ({$employment_date})</th>
          <th rowspan="2" class="data-align-right">Annual Employment Growth Rate ({$growth_rate_date})</th>
          <th colspan="3" class="lmo-report-job-openings-header data-align-center">Job Openings ({$openings_date})</th>
        </tr>
        <tr>
          <th class="data-align-right">Expansion</th>
          <th class="data-align-right">Replacement</th>
          <th class="data-align-right">Total</th>
        </tr>
      </thead>
      <tbody>
    END;
    foreach ($this->report->ssot_data[$this->getDataset()] as $i => $region) {
      $region_name = ssotRegionName($region['region']);

      // Special case: Inject &shy; after '/' to avoid long columns.
      $region_name = str_replace('/', '/&shy;', $region_name);

      if ($region['region'] <> "british_columbia") {
        $output .= '<tr class="clearfix interactive-map-row-'. $region['region'] . '">';
        $output .= '<td class="data-align-left lmo-mobile">Regions</td>';
        $output .= '<td class="data-align-left lmo-report-region" data-label="Regions"><a href="#regional_outlook-content-sidenav-anchor-' . $i . '">' . $region_name . '</a></td>';
        $output .= '<td class="data-align-right lmo-report-employment" data-label="Employment (' . $employment_date . ')">' . ssotFormatNumber($region['employment'], $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-growth" data-label="Annual Employment Growth Rate (' . $growth_rate_date . ')">' . ssotFormatNumber($region['growth_rate'], $options2) . '</td>';
        $output .= '<td class="data-align-right lmo-report-expansion" data-label="Job Openings by Expansion (' . $expansion_date . ')">' . ssotFormatNumber($region['expansion'], $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-replacement" data-label="Job Openings by Replacement (' . $replacement_date . ')">' . ssotFormatNumber($region['replacement'], $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-openings" data-label="Total Job Openings (' . $openings_date . ')">' . ssotFormatNumber($region['openings'], $options1) . '</td>';
        $output .= '</tr>';
      }
    }
    $output .= '</tbody>';

    $output .= '<tfoot>';
    $output .= '<tr class="clearfix lmo-region-job-openings-footer interactive-map-row-'. $region['region'] . '">';
    $output .= '<td class="data-align-left lmo-report-region" data-label="Regions">' . $region_name . '</td>';
    $output .= '<td class="data-align-right lmo-report-employment" data-label="Employment (' . $employment_date . ')">' . ssotFormatNumber($region['employment'], $options1) . '</td>';
    $output .= '<td class="data-align-right lmo-report-growth" data-label="Annual Employment Growth Rate (' . $growth_rate_date . ')">' . ssotFormatNumber($region['growth_rate'], $options2) . '</td>';
    $output .= '<td class="data-align-right lmo-report-expansion" data-label="Job Openings by Expansion (' . $expansion_date . ')">' . ssotFormatNumber($region['expansion'], $options1) . '</td>';
    $output .= '<td class="data-align-right lmo-report-replacement" data-label="Job Openings by Replacement (' . $replacement_date . ')">' . ssotFormatNumber($region['replacement'], $options1) . '</td>';
    $output .= '<td class="data-align-right lmo-report-openings" data-label="Total Job Openings (' . $openings_date . ')">' . ssotFormatNumber($region['openings'], $options1) . '</td>';
    $output .= '</tr>';
    $output .= '</tfoot>';

    $output .= '</table>';
    $source_text = $this->report->ssot_data['sources']['label'] ?? WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $output .= <<<END
    <div class="lm-source"><strong>Source:</strong>&nbsp;$source_text</div>
    END;
    return $output;
  }
}
