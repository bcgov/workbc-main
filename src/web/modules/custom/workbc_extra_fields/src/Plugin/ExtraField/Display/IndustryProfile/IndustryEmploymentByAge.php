<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_by_age",
 *   label = @Translation("Employment by Age Table"),
 *   description = @Translation("An extra field to display industry employment by age table."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentByAge extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Age Group');
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

      $value = 100;
      $value -= $entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct_average'];
      $value -= $entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct_average'];

      $bcAvgUnder25 = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct_average'],1) . '%';
      $bcAvg25thru55 = ssotFormatNumber($value,1) . "%";
      $bcAvgOver55 = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct_average'],1) . '%';

      $value = 100;
      $value -= $entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct'];
      $value -= $entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct'];

      $industryAvgUnder25 = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct'],1) . '%';
      $industryAvg25thru55 = ssotFormatNumber($value,1) . "%";
      $industryAvgOver55 = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct'],1) . '%';
    }
    else {
      $bcAvgUnder25 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;;
      $bcAvg25thru55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $bcAvgOver55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $industryAvgUnder25 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;;
      $industryAvg25thru55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $industryAvgOver55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }

    $datestr1 = ssotParseDateRange($entity->ssot_data['schema'], 'labour_force_survey_industry', 'workforce_employment_under_25_pct_average');
    $datestr2 = ssotParseDateRange($entity->ssot_data['schema'], 'labour_force_survey_industry', 'workforce_employment_under_25_pct');

    $content = '<table>';
    $content .= "<tr><th>Age Group</th><th>Industry Average (" . $datestr1 . ")</th><th>B.C. Average (" . $datestr2 . ")</th></tr>";
    $content .= '<tr><td>15 - 24 years</td><td>' . $industryAvgUnder25 . '</td><td>' . $bcAvgUnder25 . '</td></tr>';
    $content .= '<tr><td>25 - 54 years</td><td>' . $industryAvg25thru55 . '</td><td>' . $bcAvg25thru55 . '</td></tr>';
    $content .= '<tr><td>55+ years</td><td>' . $industryAvgOver55 . '</td><td>' . $bcAvgOver55 . '</td></tr>';
    $content .= '</table>';

    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
