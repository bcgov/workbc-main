<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Base class for Table 5.X-1 Top five industries by total job openings, 2024-2034.
 */
class RegionTopFiveIndustriesBaseTable extends ExtraFieldDisplayFormattedBase {

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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data[$this->getDatasetName()])) {

      $options1 = array(
        'decimals' => 0,
        'na_if_empty' => TRUE,
      );
      $options2 = array(
        'decimals' => 1,
        'suffix' => '%',
        'na_if_empty' => TRUE,
      );

      $output = '<div id="' . $this->getRegion() . '">';
      $output .= <<<END
      <table class="lmo-report">
        <thead>
          <tr>
            <th rowspan="2" class="data-align-left">Industry</th>
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
      foreach ($entity->ssot_data[$this->getDatasetName()] as $industry) {
        $output .= '<tr>';
        $output .= '<td class="data-align-left lmo-report-industry" data-label="Industry">' . $industry['industry'] . '</td>';
        $output .= '<td class="data-align-right lmo-report-employment" data-label="Employment (2024)">' . ssotFormatNumber($industry['employment'], $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-growth" data-label="Annual Employment Growth Rate (2024-2034)">' . ssotFormatNumber($industry['growth_rate'], $options2) . '</td>';
        $output .= '<td class="data-align-right lmo-report-expansion" data-label="Job Openings by Expansion (2024-2034)">' . ssotFormatNumber($industry['expansion'], $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-replacement" data-label="Job Openings by Replacement (2024-2034)">' . ssotFormatNumber($industry['replacement'], $options1) . '</td>';
        $output .= '<td class="data-align-right lmo-report-openings" data-label="Total Job Openings (2024-2034)">' . ssotFormatNumber($industry['openings'], $options1) . '</td>';
        $output .= '</tr>';
      }
      $output .= '</tbody></table></div>';
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

  private function getRegion() {
    return str_replace('lmo_report_2024_job_openings_', '', $this->getDatasetName());
  }
}
