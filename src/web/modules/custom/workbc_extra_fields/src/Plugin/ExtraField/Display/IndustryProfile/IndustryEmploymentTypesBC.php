<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_types_bc",
 *   label = @Translation("Employment Type BC"),
 *   description = @Translation("An extra field to display industry employment types BC."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentTypesBC extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_industry', 'employment_part_time_pct_average');
    return $this->t("B.C. (" . $datestr . ")");
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
      $ft = 100;
      $ft -= $entity->ssot_data['labour_force_survey_industry']['employment_part_time_pct_average'];
      $ft -= $entity->ssot_data['labour_force_survey_industry']['employment_self_employment_pct_average'];
      $ft -= $entity->ssot_data['labour_force_survey_industry']['employment_temporary_pct_average'];
      $employmentFullTime = $ft . '%';
      $employmentPartTime = $entity->ssot_data['labour_force_survey_industry']['employment_part_time_pct_average'] . '%';
      $employmentSelfEmployed = $entity->ssot_data['labour_force_survey_industry']['employment_self_employment_pct_average'] . '%';
      $employmentTemporary =  $entity->ssot_data['labour_force_survey_industry']['employment_temporary_pct_average'] . '%';
    }
    else {
      $employmentFullTime = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;;
      $employmentPartTime = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $employmentSelfEmployed = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $employmentTemporary = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }

    $content = '<table>';
    $content .= '<tr><td>Full Time</td><td>' . $employmentFullTime . '</td></tr>';
    $content .= '<tr><td>Part Time</td><td>' . $employmentPartTime . '</td></tr>';
    $content .= '<tr><td>Self-employed</td><td>' . $employmentSelfEmployed . '</td></tr>';
    $content .= '<tr><td>Temporary Jobs</td><td>' . $employmentTemporary . '</td></tr>';
    $content .= '</table>';

    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
