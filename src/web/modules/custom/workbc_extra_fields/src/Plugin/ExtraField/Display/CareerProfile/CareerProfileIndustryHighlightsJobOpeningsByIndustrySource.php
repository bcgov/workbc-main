<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "job_openings_by_industry_source",
 *   label = @Translation("Industry Highlights - Source: Job Openings by Industry"),
 *   description = @Translation("Provenance metadata for field Industry Highlights - Job Openings by Industry."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileIndustryHighlightsJobOpeningsByIndustrySource extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Source: Forecasted Job Openings by Industry');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['sources']['openings'])) {
      $output = $entity->ssot_data['sources']['openings']['label'];
    }
    else {
      $output = "";
    }

    return [
      ['#markup' => $output],
    ];
  }

}
