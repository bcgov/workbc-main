<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_latest_trends_total",
 *   label = @Translation("Latest Employment Trends Total"),
 *   description = @Translation("An extra field to display industry latest employment trends total."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentLatestTrendsTotal extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Employment');
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
      'positive_sign' => TRUE,
      'na_if_empty' => TRUE,
    );

    $industry = ssotIndustryLMMKey($entity->ssot_data['industry_outlook']['industry']);

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['monthly_labour_market_updates'])) {
      $idx = ssotLatestMonthlyLabourMarketUpdate($entity->ssot_data['monthly_labour_market_updates']);
      $output = "<div>(change since last month)</div>";
      $value = isset($entity->ssot_data['monthly_labour_market_updates'][$idx]['industry_abs_' . $industry]) ? $entity->ssot_data['monthly_labour_market_updates'][$idx]['industry_abs_' . $industry] : NULL;
      $output .= ssotFormatNumber($value, $options);
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}





