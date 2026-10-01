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
 *   label = @Translation("[SSOT] Employment by Industry Table"),
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
      $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_regional_industry_region');

      $industries = ssotProcessEmploymentIndustry($entity->ssot_data);

      $options1 = array(
        'decimals' => 0,
        'na_if_empty' => TRUE,
      );
      $options2 = array(
        'decimals' => 1,
        'suffix' => "%",
        'na_if_empty' => TRUE,
      );

      $content = "<table>";
      $content .= "<thead><tr><th>Industry</th><th class=''>Employment (" . $datestr . ")</th><th class=''>% Share of Employment for this Industry</th></tr></thead>";
      foreach ($industries as $key => $industry) {
        $link = "<a href='" . $industry['link'] . "'>";
        $close = "</a>";
        $content .= "<tr>";
        $content .= "<td class='industry'>" . $link . $industry['name'] . $close . "</td>";
        $employment = ssotFormatNumber($industry['employment'], $options1);
        $content .= "<td class='employment data-row data-align-right' data-label='Employment (" . $datestr . ")'>" . $employment . "</td>";
        $share = ssotFormatNumber($industry['share'], $options2);
        $content .= "<td class='employment-share data-row data-align-right' data-label='% Share of Employment'>" . $share . "</td>";
        $content .= "</tr>";
        if ($key <> array_key_last($industries)) {
          $content .= '<tr class="horizontal-line"></tr>';
        }
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
