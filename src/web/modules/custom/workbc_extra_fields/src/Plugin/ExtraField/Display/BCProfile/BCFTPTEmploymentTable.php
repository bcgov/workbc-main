<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "BC_ft_pt_employment_table",
 *   label = @Translation("FT/PT Employment Table"),
 *   description = @Translation("An extra field to display FT/PT employment table."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCFTPTEmploymentTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('FT/PT Employment Table');
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
      'suffix' => "%",
      'na_if_empty' => TRUE,
    );
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_bc_employment'])) {
      $value = $entity->ssot_data['labour_force_survey_bc_employment']['full_time_employment_pct'];
      $fulltime = ssotFormatNumber($value, $options);
      $value = $entity->ssot_data['labour_force_survey_bc_employment']['part_time_employment_pct'];
      $parttime = ssotFormatNumber($value, $options);
      $content = "<table class='region-profile-table' role='presentation'>";
      $content .= "<tr><td>Full-time employment (average):</td><td class='region-profile-table-value'>" . $fulltime . "</td></tr>";
      $content .= "<tr><td>Part-time employment (average):</td><td class='region-profile-table-value'>" . $parttime . "</td></tr>";
      $content .= "</table>";
      $output = $content;
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
