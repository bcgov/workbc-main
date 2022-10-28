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
    $total_employment_change = !empty($data['employment_change_pct_total_employment'])?ssotFormatNumber($data['employment_change_pct_total_employment'], 1, true):WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $fulltime_value = !empty($data['employment_change_pct_full_time_jobs'])?ssotFormatNumber($data['employment_change_pct_full_time_jobs'], 1, true):WORKBC_EXTRA_FIELDS_NOT_AVAILABLE; 
    $parttime_value = !empty($data['employment_change_pct_part_time_jobs'])?ssotFormatNumber($data['employment_change_pct_part_time_jobs'], 1, true):WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;  

    //output
    $output = '
    <div class="LME--total-employed">
    <span class="LME--total-employed-label"><strong>'.$this->t("% Employment Change").'</strong></span>
    <span class="LME--total-employed-label">'.$this->t("(from last month)").'</span>
    <span class="LME--total-employed-value blue">'.$total_employment_change.'%</span>
    <div class="LME--total-employed-time">
      <div class="LME--total-employed-time-full"><span>'.$this->t("Full Time").'</span><span class="LME--total-employed-time-full-value">'.$fulltime_value.'%</span></div>
      <div class="LME--total-employed-time-part"><span>'.$this->t("Part Time").'</span><span class="LME--total-employed-time-part-value">'.$parttime_value.'%</span></div>
    </div>
    </div>';

    return [
      ['#markup' => $output],
    ];
  }

}
