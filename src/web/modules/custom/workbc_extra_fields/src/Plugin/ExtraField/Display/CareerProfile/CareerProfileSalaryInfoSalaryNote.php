<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "salary_info_annual_salary_note",
 *   label = @Translation("Salary Info - Note: Annual Salary"),
 *   description = @Translation("Provenance metadata for field Salary Info - Annual Salary."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileSalaryInfoSalaryNote extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Note: Annual Salary');
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

      if (!empty($entity->ssot_data) && isset($entity->ssot_data['wages']['source_information'])) {
        $output = $entity->ssot_data['wages']['source_information'];
      }
      else {
        $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      }




    return [
      ['#markup' => $output],
    ];
  }

}
