<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_job_openings",
 *   label = @Translation("Job Openings"),
 *   description = @Translation("An extra field to display industry job openings."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryJobOpenings extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'industry_outlook', 'openings');
    return array('#markup' => $this->t("Job Openings") . "<br>(" . $datestr . ")");
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
      'na_if_empty' => TRUE,
    );
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['industry_outlook']['openings'])) {
      $output = ssotFormatNumber($entity->ssot_data['industry_outlook']['openings'], $options);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
