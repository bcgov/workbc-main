<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_growth",
 *   label = @Translation("Employment Growth"),
 *   description = @Translation("An extra field to display industry employment growth."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentGrowth extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'industry_outlook', 'annual_employment_growth_rate_pct_10y');
    return $this->t("Employment Growth (" . $datestr . ")");
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['industry_outlook']['annual_employment_growth_rate_pct_10y'])) {
      $output = ssotFormatNumber($entity->ssot_data['industry_outlook']['annual_employment_growth_rate_pct_10y'], 1, true) . "%";
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }
}
