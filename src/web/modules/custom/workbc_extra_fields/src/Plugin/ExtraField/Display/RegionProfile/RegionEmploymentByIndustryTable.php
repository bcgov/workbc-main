<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_employment_by_industry_table",
 *   label = @Translation("Employment by Industry Table"),
 *   description = @Translation("An extra field to display employment by industry table."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionEmploymentByIndustryTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Industry Table');
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

      $content = "<table>";
      $content .= "<tr><th>Industry</th><th>Employment (" . $datestr . ")</th><th>% Share of Employment for this Industry</th></tr>";
      foreach ($industries as $industry) {
        $link = "<a href='" . $industry['link'] . "'>";
        $close = "</a>";
        $content .= "<tr>";
        $content .= "<td>" . $link . $industry['name'] . $close . "</td>";
        $content .= "<td>" . "n/a" . "</td>";
        $content .= "<td>" . ssotFormatNumber($industry['share'],1) . "%</td>";
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
