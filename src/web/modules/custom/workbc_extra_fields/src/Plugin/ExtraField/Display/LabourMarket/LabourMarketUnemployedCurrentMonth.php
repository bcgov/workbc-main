<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_unemployed_current_month",
 *   label = @Translation("Unemployed Current Month"),
 *   description = @Translation("An extra field to display industry unemployed current month."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketUnemployedCurrentMonth extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Unemployed Current Month');
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
    $current_previous_months = $entity->ssot_data['current_previous_months_names'];
    $total_unemployed = !empty($data['total_unemployed'])?ssotFormatNumber($data['total_unemployed']) : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $unemployed_rate_value = !empty($data['employment_rate_pct_unemployment'])?$data['employment_rate_pct_unemployment'].'%': WORKBC_EXTRA_FIELDS_NOT_AVAILABLE; 
    $unemployed_part_value = !empty($data['employment_rate_pct_participation'])?$data['employment_rate_pct_participation']:WORKBC_EXTRA_FIELDS_NOT_AVAILABLE; 
    $source_text = !empty($entity->ssot_data['sources']['no-datapoint'])?$entity->ssot_data['sources']['no-datapoint']:WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;

    //output
    $output = '
    <div class="LME--total-unemployed">
    <span class="LME--total-unemployed-label">'.$this->t("Total Unemployed (@currentmonthyear)", ['@currentmonthyear' => $current_previous_months['current_month_year']]).'</span>
    <span class="LME--total-unemployed-value blue">'.$total_unemployed.'</span>
    <div class="LME--total-unemployed-rate">
      <div class="LME--total-unemployed-rate"><span>'.$this->t("Unemployment Rate").'</span><span class="LME--total-unemployed-rate-value">'.$unemployed_rate_value.'%</span></div>
      <div class="LME--total-unemployed-part"><span>'.$this->t("Participation Rate").'</span><span class="LME--total-unemployed-part-value">'.$unemployed_part_value.'%</span></div>
    </div>
    <span class="LME--total-employed-bottom-source"><strong>'.$this->t("Source").': </strong>'.$source_text.'</span>
    </div>';

    return [
      ['#markup' => $output],
    ];
  }

}
