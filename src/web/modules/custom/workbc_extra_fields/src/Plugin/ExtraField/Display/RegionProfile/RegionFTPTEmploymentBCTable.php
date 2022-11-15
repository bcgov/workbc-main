<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_ft_pt_employment_table_bc",
 *   label = @Translation("BC FT/PT Employment Table"),
 *   description = @Translation("An extra field to display BC FT/PT employment table."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionFTPTEmploymentBCTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('BC FT/PT Employment Table');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_bc_employment'])) {
      $fulltime = $entity->ssot_data['labour_force_survey_bc_employment']['full_time_employment_pct'];
      $parttime = 100 - $fulltime;

      $content = "<table>";
      $content .= "<tr><td>Full-time employment</td><td>" . ssotFormatNumber($fulltime,0) . "%</td></tr>";
      $content .= "<tr><td>Part-time employment</td><td>" . ssotFormatNumber($parttime,0) . "%</td></tr>";
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
