<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "salary_info_hourly_rate",
 *   label = @Translation("Salary Info - Hourly Rate"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileSalaryInfoHourly extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Provincial Hourly Rate');
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

    $output = "n/a";

    if (!empty($entity->ssot_data)) {
      $hourly1 = $entity->ssot_data['esdc_wage_rate_high_2021'];
      $hourly2 = $entity->ssot_data['esdc_wage_rate_median_2021'];
      $hourly3 = $entity->ssot_data['esdc_wage_rate_low_2021'];
    }
    else {
      $hourly1 = "n/a";
      $hourly2 = "n/a";
      $hourly3 = "n/a";
    }

    $content .= '<table>';
    $content .= '<tr><td>High</td><td>$' . $hourly1 . '/hr</td></tr>';
    $content .= '<tr><td>Median</td><td>$' . $hourly2 . '/hr</td></tr>';
    $content .= '<tr><td>Low</td><td>$' . $hourly3 . '/hr</td></tr>';
    $content .= '</table>';

    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
