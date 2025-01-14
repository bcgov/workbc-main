<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_regions_table",
 *   label = @Translation("Total Job Openings by Development Region, B.C. (2024-2034)"),
 *   description = @Translation("An extra field to display job openings regional table."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class BCJobOpeningsRegionTable extends ExtraFieldDisplayFormattedBase {

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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['lmo_report_2024_job_openings_regions'])) {

      $options1 = array(
        'decimals' => 0,
        'na_if_empty' => TRUE,
      );
      $options2 = array(
        'decimals' => 1,
        'suffix' => '%',
        'na_if_empty' => TRUE,
      );

      $output = <<<END
      <table class="lmo-report">
        <thead>
          <tr>
            <th rowspan="2" class="data-align-left">Region</th>
            <th rowspan="2" class="data-align-right">Employment (2024)</th>
            <th rowspan="2" class="data-align-right">Annual Employment Growth Rate (2024-2034)</th>
            <th colspan="3" class="lmo-report-job-openings-header data-align-center">Job Openings (2024-2034)</th>
          </tr>
          <tr>
            <th class="data-align-right">Expansion</th>
            <th class="data-align-right">Replacement</th>
            <th class="data-align-right">Total</th>
          </tr>
        </thead>
        <tbody>
      END;
      foreach ($entity->ssot_data['lmo_report_2024_job_openings_regions'] as $region) {
        $region_name = ssotRegionName($region['region']);
        if ($region['region'] <> "british_columbia") {
          $output .= '<tr class="interactive-map-row-'. $region['region'] . '">';
          $output .= '<td class="data-align-left lmo-report-region" data-label="Regions"><a href="#' . $region['region']  . '">' . $region_name . '</a></td>';
          $output .= '<td class="data-align-right lmo-report-employment" data-label="Employment (2024)">' . ssotFormatNumber($region['employment'], $options1) . '</td>';
          $output .= '<td class="data-align-right lmo-report-growth" data-label="Annual Employment Growth Rate (2024-2034)">' . ssotFormatNumber($region['growth_rate'], $options2) . '</td>';
          $output .= '<td class="data-align-right lmo-report-expansion" data-label="Job Openings by Expansion (2024-2034)">' . ssotFormatNumber($region['expansion'], $options1) . '</td>';
          $output .= '<td class="data-align-right lmo-report-replacement" data-label="Job Openings by Replacement (2024-2034)">' . ssotFormatNumber($region['replacement'], $options1) . '</td>';
          $output .= '<td class="data-align-right lmo-report-openings" data-label="Total Job Openings (2024-2034)">' . ssotFormatNumber($region['openings'], $options1) . '</td>';
          $output .= '</tr>';
        }
        else {
          $bc_data = $region;
        }
      }
      $output .= '</tbody>';

      $output .= '<tfoot>';
      $output .= '<tr class="lmo-region-job-openings-footer interactive-map-row-'. $region['region'] . '">';
      $output .= '<td class="data-align-left lmo-report-region" data-label="Regions">' . $region_name . '</td>';
      $output .= '<td class="data-align-right lmo-report-employment" data-label="Employment (2024)">' . ssotFormatNumber($region['employment'], $options1) . '</td>';
      $output .= '<td class="data-align-right lmo-report-growth" data-label="Annual Employment Growth Rate (2024-2034)">' . ssotFormatNumber($region['growth_rate'], $options2) . '</td>';
      $output .= '<td class="data-align-right lmo-report-expansion" data-label="Job Openings by Expansion (2024-2034)">' . ssotFormatNumber($region['expansion'], $options1) . '</td>';
      $output .= '<td class="data-align-right lmo-report-replacement" data-label="Job Openings by Replacement (2024-2034)">' . ssotFormatNumber($region['replacement'], $options1) . '</td>';
      $output .= '<td class="data-align-right lmo-report-openings" data-label="Total Job Openings (2024-2034)">' . ssotFormatNumber($region['openings'], $options1) . '</td>';
      $output .= '</tr>';
      $output .= '</tfoot>';

      $output .= '</table>';
      $source_text = $entity->ssot_data['sources']['label'] ?? WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $output .= <<<END
      <div class="lm-source"><strong>Source:</strong>&nbsp;$source_text</div>
      END;
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return ['#markup' => $output];
  }

  private function getDatasetName() {
    return str_replace('_table', '', $this->getPluginId());
  }
}
