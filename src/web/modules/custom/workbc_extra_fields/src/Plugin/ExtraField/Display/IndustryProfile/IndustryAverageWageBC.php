<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_average_wage_bc",
 *   label = @Translation("Average Wage BC"),
 *   description = @Translation("An extra field to display industry average wage BC."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryAverageWageBC extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Average Wage BC');
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
      $avgMen = '$' . $entity->ssot_data['labour_force_survey_industry']['earnings_men_average'] . '/hr';
      $avgWomen = '$' . $entity->ssot_data['labour_force_survey_industry']['earnings_women_average'] . '/hr';
      $avgYouth = '$' . $entity->ssot_data['labour_force_survey_industry']['earnings_youth_average'] . '/hr';
    }
    else {
      $avgMen = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;;
      $avgWomen = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $avgYouth = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }

    $content = '<table>';
    $content .= '<tr><td>Men</td><td>' . $avgMen . '</td></tr>';
    $content .= '<tr><td>Women</td><td>' . $avgWomen . '</td></tr>';
    $content .= '<tr><td>Youth</td><td>' . $avgYouth . '</td></tr>';
    $content .= '</table>';

    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
