<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_types_can",
 *   label = @Translation("[SSOT] Employment Type National"),
 *   description = @Translation("An extra field to display industry employment types national."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentTypesCAN extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('National');
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
      $pt = round($entity->ssot_data['labour_force_survey_industry']['employment_part_time_pct']);
      $ft = 100 - $pt;
      $employmentFullTime = $ft . '%';
      $employmentPartTime = $pt . '%';
      $employmentSelfEmployed = $entity->ssot_data['labour_force_survey_industry']['employment_self_employment_pct'] . '%';
      $employmentTemporary =  $entity->ssot_data['labour_force_survey_industry']['employment_temporary_pct'] . '%';
    }
    else {
      $employmentFullTime = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;;
      $employmentPartTime = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $employmentSelfEmployed = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $employmentTemporary = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }

    $content = '<table class="industry-profile-table" role="presentation">';
    $content .= '<tr><td>Full Time</td><td class="industry-profile-table-value">' . $employmentFullTime . '</td></tr>';
    $content .= '<tr><td>Part Time</td><td class="industry-profile-table-value">' . $employmentPartTime . '</td></tr>';
    $content .= '<tr><td>Self-employed</td><td class="industry-profile-table-value">' . $employmentSelfEmployed . '</td></tr>';
    $content .= '<tr><td>Temporary Jobs</td><td class="industry-profile-table-value">' . $employmentTemporary . '</td></tr>';
    $content .= '</table>';

    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
