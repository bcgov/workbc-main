<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labour_market_expected_openings",
 *   label = @Translation("Labour Market Info - Expected Job Openings"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileLabourMarketExpectedOpenings extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'career_provincial', 'expected_job_openings_10y');
    return $this->t('Expected Job Openings (:datestr)', array(":datestr" => $datestr));
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
      'no_negative' => TRUE,
      'na_if_empty' => TRUE,
    );    
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_provincial']['expected_job_openings_10y'])) {
      $output = ssotFormatNumber($entity->ssot_data['career_provincial']['expected_job_openings_10y'], $options);     
    }
    else {
      $output = "";
    }

    return [
      ['#markup' => $output],
    ];
  }

}
