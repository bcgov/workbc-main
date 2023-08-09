<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_employment_by_region_table",
 *   label = @Translation("Employment by Region Table"),
 *   description = @Translation("An extra field to display employment by region table."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCEmploymentByRegionTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Region');
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
  public function viewElements(ContentEntityInterface $entity) {

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['regional_top_industries'])) {
      $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_top_industries', 'openings');

      $bc = $entity->ssot_data['labour_force_survey_bc_employment'];

      $regions = $entity->ssot_data['labour_force_survey_regions_employment'];
      usort($regions, function($a, $b) {
        return $a['region'] <=> $b['region'];
      });

      $options = array(
        'decimals' => 0,
        'suffix' => '%',
        'na_if_empty' => TRUE,
      );


      // font color, icon color, icon positioning
      $tooltip = '<span class="workbc-tooltip bc-profile--employment-tooltip">';
      $tooltip .= '<div class="workbc-tooltip-content bc-profile--employment-tooltip-content">';
      $tooltip .= "<em>Employment rate</em> refers to the percentage of the population 15 years and older that are employed in full-time or part-time work.";
      $tooltip .= "</div>";
      $tooltip .= "</span>";

      $content = "<table>";
      $content .= "<thead>";
      $content .= "<tr class='table-header'><th>Region</th><th>Full-time Employment Rate" . $tooltip . "</th><th>Part-time Employment Rate</th></tr>";
      $content .= "</thead>";
      foreach ($regions as $region) {
        $nid = \Drupal::entityQuery('node')
          ->condition('title', ssotRegionName($region['region']))
          ->sort('nid', 'DESC')
          ->execute();
        $nid = reset($nid);

        $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$nid);
        $link = "<a href='" . $alias . "'>";
        $close = "</a>";

        $content .= "<tr  class='interactive-map-row-" . $region['region'] . "' class='bc-profile-employment-region-row'>";
        $content .= "<td class='region-name'>" . $link . ssotRegionName($region['region']) . $close . "</td>";
        $percent = ssotFormatNumber($region['full_time_employment_pct'], $options);
        $content .= "<td class='full-time-rate' data-label='Full-time Employment Rate'>" . $percent . "</td>";
        $percent = ssotFormatNumber($region['part_time_employment_pct'], $options);
        $content .= "<td class='part-time-rate' data-label='Part-time Employment Rate'>" . $percent . "</td>";
        $content .= "</tr>";
      }

      $content .= "<tfoot>";
      $content .= "<tr class='bc-profile-employment-region-footer'>";
      $content .= "<td class='region-name'>B.C. Average</td>";
      $percent = ssotFormatNumber($entity->ssot_data['labour_force_survey_bc_employment']['full_time_employment_pct'], $options);
      $content .= "<td class='full-time-rate' data-label='Full-time Employment Rate'>" . $percent . "</td>";
      $percent = ssotFormatNumber($entity->ssot_data['labour_force_survey_bc_employment']['part_time_employment_pct'], $options);
      $content .= "<td class='part-time-rate' data-label='Part-time Employment Rate'>" . $percent . "</td>";
      $content .= "</tr>";
      $content .= "</tfoot>";
      $content .= "</table>";
      
      $output = $content;
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
