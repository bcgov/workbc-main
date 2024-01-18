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
      $options = array(
        'decimals' => 1,
        'suffix' => "%",
        'na_if_empty' => TRUE,
      );
      $value = 100;
      $value -= $entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct_average'];
      $value -= $entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct_average'];

      $bcAvgUnder25 = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct_average'], $options);
      $bcAvg25thru55 = ssotFormatNumber($value, $options);
      $bcAvgOver55 = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct_average'], $options);

      $value = 100;
      $value -= $entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct'];
      $value -= $entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct'];

      $industryAvgUnder25 = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct'], $options);
      $industryAvg25thru55 = ssotFormatNumber($value, $options);
      $industryAvgOver55 = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct'], $options);
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

    $label1 = "Industry Average (" . $datestr1 . ")";
    $label2 = "B.C. Average (" . $datestr2 . ")";

    $content = '<table class="industry-table-employment-age table" >';
    $content .= "<thead>";
    $content .= "<tr><th>Age Group</th><th class='data-align-right'>" . $label1 . "</th><th class='data-align-right'>" . $label2 . "</th></tr>";
    $content .= "</thead>";
    $content .= "<tbody>";
    $content .= '<tr class="table-row "><td class="mobile-label">15 - 24 years</td>';
    $content .= '<td data-label="' . $label1 . '" class="data-row data-align-right">' . $industryAvgUnder25 . '</td>';
    $content .= '<td data-label="' . $label2 . '" class="data-row data-row-last data-align-right">' . $bcAvgUnder25 . '</td></tr>';
    $content .= '<tr class="table-row "><td class="mobile-label">25 - 54 years</td>';
    $content .= '<td data-label="' . $label1 . '" class="data-row data-align-right">' . $industryAvg25thru55 . '</td>';
    $content .= '<td data-label="' . $label2 . '" class="data-row data-row-last data-align-right">' . $bcAvg25thru55 . '</td></tr>';
    $content .= '<tr class="table-row "><td class="mobile-label">55+ years</td>';
    $content .= '<td data-label="' . $label1 . '" class="data-row data-align-right">' . $industryAvgOver55 . '</td>';
    $content .= '<td data-label="' . $label2 . '" class="data-row data-row-last data-align-right">' . $bcAvgOver55 . '</td></tr>';
    $content .= '</tbody>';
    $content .= '</table>';

    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
