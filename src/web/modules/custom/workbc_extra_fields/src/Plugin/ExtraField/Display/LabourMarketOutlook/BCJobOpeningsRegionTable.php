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
 *   label = @Translation("Figure 5-1. Total Job Openings by Development Region, B.C., 2024-2034"),
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

    $label = $this->getEntity()->getParentEntity()->ssot_data['schema']['definitions']['lmo_report_2024_job_openings_regions']['description'];
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
      <table>
        <thead>
          <tr>
            <th rowspan="2" class="data-align-left">Region</th>
            <th rowspan="2" class="data-align-right">Employment (2024)</th>
            <th rowspan="2" class="data-align-right">Annual Employment Growth Rate (2024-2034)</th>
            <th colspan="3" class="data-align-center">Job Openings (2024-2034)</th>
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
        $output .= '<tr class="interactive-map-row-'. $region['region'] . '">';
        if ($region['region'] <> "british_columbia") {
          $output .= '<td class="data-align-left"><a href="#' . $region['region']  . '">' . ssotRegionName($region['region']) . '</a></td>';
        }
        else {
          $output .= '<td class="data-align-left">' . ssotRegionName($region['region']) . '</td>';
        }
        $output .= '<td class="data-align-right">' . ssotFormatNumber($region['employment'], $options1) . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($region['growth_rate'], $options2) . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($region['expansion'], $options1) . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($region['replacement'], $options1) . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($region['openings'], $options1) . '</td>';
        $output .= '</tr>';
      }
      $output .= '</tbody></table>';
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
