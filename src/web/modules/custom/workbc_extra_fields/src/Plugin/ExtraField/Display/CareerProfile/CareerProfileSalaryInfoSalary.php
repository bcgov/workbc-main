<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "salary_info_annual_salary",
 *   label = @Translation("Salary Info - Annual Salary"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileSalaryInfoSalary extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Annual Salary');
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

    $options = array(
      'decimals' => 0,
      'prefix' => "$",
      'na_if_empty' => TRUE,
    );
      if (!empty($entity->ssot_data) && isset($entity->ssot_data['wages']['calculated_median_annual_salary'])) {
        $output = ssotFormatNumber($entity->ssot_data['wages']['calculated_median_annual_salary'], $options);
      }
      else {
        $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      }




    return [
      ['#markup' => $output],
    ];
  }

}
