<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_employment_change",
 *   label = @Translation("Employment Change"),
 *   description = @Translation("An extra field to display industry employment change."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketEmploymentChange extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment Change');
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

    //values
    $year = $entity->ssot_data['monthly_labour_market_updates'][0]['year'];
    $month = date ('F', $entity->ssot_data['monthly_labour_market_updates'][0]['month']);
    $total_employment_change = Number_format($entity->ssot_data['monthly_labour_market_updates'][0]['employment_change_abs_total_employment']);
    $fulltime_value = Number_format($entity->ssot_data['monthly_labour_market_updates'][0]['employment_change_abs_full_time_jobs']); 
    $parttime_value = Number_format($entity->ssot_data['monthly_labour_market_updates'][0]['employment_change_abs_part_time_jobs']); 
    $source_text = $entity->ssot_data['sources']['no-datapoint'];  

    //output
    $output = '
    <div class="LME--total-employed">
    <span class="LME--total-employed-label"><strong>'.$this->t("Employment Change").'</strong></span>
    <span class="LME--total-employed-label">'.$this->t("(since last month)").'</span>
    <span class="LME--total-employed-value blue">'.$total_employment_change.'</span>
    <div class="LME--total-employed-time">
      <div class="LME--total-employed-time-full"><span>'.$this->t("Full Time").'</span><span class="LME--total-employed-time-full-value">'.$fulltime_value.'</span></div>
      <div class="LME--total-employed-time-part"><span>'.$this->t("Part Time").'</span><span class="LME--total-employed-time-part-value">'.$parttime_value.'</span></div>
    </div>
    <span class="LME--total-employed-bottom-source"><strong>'.$this->t("Source: ").'</strong>'.$source_text.'</span>
    </div>';


    return [
      ['#markup' => $output],
    ];
  }

}
