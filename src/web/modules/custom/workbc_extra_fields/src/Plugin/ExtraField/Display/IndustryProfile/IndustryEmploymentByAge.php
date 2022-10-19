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

    return $this->t('Employment by Age');
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

      $bcAvgUnder25 = $entity->ssot_data['labour_force_survey_industry']['workforce_employment_under_25_pct_average'] . '%';
      $bcAvg25thru55 = $value . "%";
      $bcAvgOver55 = $entity->ssot_data['labour_force_survey_industry']['workforce_employment_over_55_pct_average'] . '%';
      $natAvgUnder25 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $natAvg25thru55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $natAvgOver55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    else {
      $bcAvgUnder25 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;;
      $bcAvg25thru55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $bcAvgOver55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $natAvgUnder25 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;;
      $natAvg25thru55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $natAvgOver55 = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }

    $content = '<table>';
    $content .= '<tr><th>Age Group</th><th>B.C. Industry Average</th><th>National Average</th></tr>';
    $content .= '<tr><td>15 - 24 years</td><td>' . $bcAvgUnder25 . '</td><td>' . $natAvgUnder25 . '</td></tr>';
    $content .= '<tr><td>25 - 54 years</td><td>' . $bcAvg25thru55 . '</td><td>' . $natAvg25thru55 . '</td></tr>';
    $content .= '<tr><td>55+ years</td><td>' . $bcAvgOver55 . '</td><td>' . $natAvgOver55 . '</td></tr>';
    $content .= '</table>';

    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
