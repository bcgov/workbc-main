<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_ft_pt_employment_table",
 *   label = @Translation("FT/PT Employment Table"),
 *   description = @Translation("An extra field to display FT/PT employment table."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionFTPTEmploymentTable extends ExtraFieldDisplayFormattedBase {

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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_regional_employment'])) {
      // $fulltime = $entity->ssot_data['labour_force_survey_regional_employment']['full_time_employment_pct'];
      // $parttime = 100 - $fulltime;

      $value = $entity->ssot_data['labour_force_survey_regional_employment']['full_time_employment_pct'];
      if ($value===0||$value) {
        $fulltime = ssotFormatNumber($value) . "%" ;
        $value = 100 - $value;
        $parttime = ssotFormatNumber($value) . "%" ;
  
      }
      else {
        $fulltime = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
        $fulltime = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      }

      $content = "<table>";
      $content .= "<tr><td>Full-time employment</td><td>" . $fulltime . "</td></tr>";
      $content .= "<tr><td>Part-time employment</td><td>" . $parttime . "</td></tr>";
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
