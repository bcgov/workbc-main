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

    $label = $this->getEntity()->getParentEntity()->ssot_data['schema']['definitions'][$this->getDatasetName()]['description'];
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
      <table>
        <thead>
          <tr>
            <th rowspan="2" class="data-align-left">Industry</th>
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
      foreach ($entity->ssot_data[$this->getDatasetName()] as $industry) {
        $output .= '<tr>';
        $output .= '<td class="data-align-left">' . $industry['industry'] . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($industry['employment'], $options1) . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($industry['growth_rate'], $options2) . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($industry['expansion'], $options1) . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($industry['replacement'], $options1) . '</td>';
        $output .= '<td class="data-align-right">' . ssotFormatNumber($industry['openings'], $options1) . '</td>';
        $output .= '</tr>';
      }
      $output .= '</tbody></table></div>';
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
