<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_employment_by_industry_table",
 *   label = @Translation("Employment by Industry Table"),
 *   description = @Translation("An extra field to display employment by industry table."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCEmploymentByIndustryTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Industry');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_regional_industry_region'])) {
      $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_regional_industry_region', 'openings');
      $industries = ssotProcessEmploymentIndustry($entity->ssot_data['labour_force_survey_regional_industry_region']);

      $options = array(
        'decimals' => 1,
        'suffix' => "%",
        'na_if_empty' => TRUE,
      );
      
      $content = '<table>';
      $content .= "<tr><th>Industry</th><th class='data-align-right bc-employment-share'>% Share of Employment<br>for this Industry</th><th class='data-align-center'>Sector</th></tr>";
      foreach ($industries as $industry) {
        $link = "<a href='" . $industry['link'] . "'>";
        $close = "</a>";
        $content .= "<tr>";
        $content .= "<td>" . $link . $industry['name'] . $close . "</td>";
        $content .= "<td class='data-align-right bc-employment-share'>" . ssotFormatNumber($industry['share'], $options) . "</td>";
        $content .= "<td class='data-align-center'>" . $industry['sector'] . "</td>";
        $content .= "</tr>";
      }
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
