<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_employment_change_percent",
 *   label = @Translation("Employment Change Percent"),
 *   description = @Translation("An extra field to display industry employment change percent."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketEmploymentChangePercent extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment Change Percent');
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

    if(empty($entity->ssot_data['monthly_labour_market_updates'])){
      $output = '<div>'.WORKBC_EXTRA_FIELDS_NOT_AVAILABLE.'</div>';
      return [
        ['#markup' => $output ],
      ];
    }

    $data = $entity->ssot_data['monthly_labour_market_updates'][0];

    //values
    $total_employment_change = isset($data['employment_change_pct_total_employment'])?ssotFormatNumber($data['employment_change_pct_total_employment'], 1, true).'%':WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $fulltime_value = isset($data['employment_change_pct_full_time_jobs'])?ssotFormatNumber($data['employment_change_pct_full_time_jobs'], 1, true).'%':WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $parttime_value = isset($data['employment_change_pct_part_time_jobs'])?ssotFormatNumber($data['employment_change_pct_part_time_jobs'], 1, true).'%':WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $source_text = !empty($entity->ssot_data['sources']['no-datapoint'])?$entity->ssot_data['sources']['no-datapoint'] : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;

    //output
    $output = '
    <div class="lm-data-box text-center">
    <div class="lm-label"><strong>'.$this->t("% Employment Change").'</strong>    <div class="lm-sub-label">'.$this->t("(from last month)").'</div></div>
    <div class="lm-data-value">'.$total_employment_change.'</div>
    <div class="lm-data-container">
      <div class="lm-data-item"><div class="lm-data-item-label">'.$this->t("Full Time").'</div><div class="lm-data-item-value">'.$fulltime_value.'</div></div>
      <div class="lm-data-item"><div class="lm-data-item-label">'.$this->t("Part Time").'</div><div class="lm-data-item-value">'.$parttime_value.'</div></div>
    </div>
    </div>
    <div class="lm-source"><strong>'.$this->t("Source").': </strong>'.$source_text.'</div>';

    return [
      ['#markup' => $output],
    ];
  }

}
