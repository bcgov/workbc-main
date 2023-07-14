<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_wage",
 *   label = @Translation("Industry Average Wage"),
 *   description = @Translation("An extra field to display industry wage."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryAverageWage extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_industry', 'earnings_men');
    return $this->t("Industry Average Wage (" . $datestr . ")");
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_industry'])) {
      $avgMen = '$' . ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['earnings_men'],2) . '/hr';
      $avgWomen = '$' . ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['earnings_women'],2) . '/hr';
      $avgYouth = '$' . ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['earnings_youth'],2) . '/hr';
    }
    else {
      $avgMen = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;;
      $avgWomen = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $avgYouth = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }

    $content = '<table class="industry-profile-table">';
    $content .= '<tr><td>Men</td><td class="industry-profile-table-value">' . $avgMen . '</td></tr>';
    $content .= '<tr><td>Women</td><td class="industry-profile-table-value">' . $avgWomen . '</td></tr>';
    $content .= '<tr><td>Youth</td><td class="industry-profile-table-value">' . $avgYouth . '</td></tr>';
    $content .= '</table>';

    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
