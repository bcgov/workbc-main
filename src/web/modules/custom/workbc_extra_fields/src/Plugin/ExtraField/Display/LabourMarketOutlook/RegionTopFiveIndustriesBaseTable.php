<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

/**
 * Base class for Table 5.X-1 Top five industries by total job openings.
 */
class RegionTopFiveIndustriesBaseTable extends LabourMarketOutLookExtraFieldBase {

  public function viewDatasetElement() {
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
    $growth_rate_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'growth_rate');
    $expansion_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'expansion');
    $replacement_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'replacement');
    $openings_date = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true), 'openings');

    $output = '<div id="' . $this->getRegion() . '">';
    $output .= <<<END
    <table class="lmo-report">
      <thead>
        <tr>
          <th width="20%" rowspan="2" class="data-align-left">Industry</th>
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
    foreach ($this->report->ssot_data[$this->getDataset()] as $industry) {
      $output .= '<tr class="clearfix">';
      $output .= '<td class="data-align-left lmo-mobile">Industry</td>';
      $output .= '<td class="data-align-left lmo-report-industry" data-label="Industry">' . $industry['industry'] . '</td>';
      $output .= '<td class="data-align-right lmo-report-employment" data-label="Employment (' . $employment_date . ')">' . ssotFormatNumber($industry['employment'], $options1) . '</td>';
      $output .= '<td class="data-align-right lmo-report-growth" data-label="Annual Employment Growth Rate (' . $growth_rate_date . ')">' . ssotFormatNumber($industry['growth_rate'], $options2) . '</td>';
      $output .= '<td class="data-align-right lmo-report-expansion" data-label="Job Openings by Expansion (' . $expansion_date . ')">' . ssotFormatNumber($industry['expansion'], $options1) . '</td>';
      $output .= '<td class="data-align-right lmo-report-replacement" data-label="Job Openings by Replacement (' . $replacement_date . ')">' . ssotFormatNumber($industry['replacement'], $options1) . '</td>';
      $output .= '<td class="data-align-right lmo-report-openings" data-label="Total Job Openings (' . $openings_date . ')">' . ssotFormatNumber($industry['openings'], $options1) . '</td>';
      $output .= '</tr>';
    }
    $output .= '</tbody></table></div>';
    $source_text = $this->report->ssot_data['sources']['label'] ?? WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $output .= <<<END
    <div class="lm-source"><strong>Source:</strong>&nbsp;$source_text</div>
    END;
    return $output;
  }

  private function getRegion() {
    return str_replace('lmo_report_job_openings_', '', $this->getDataset());
  }
}
