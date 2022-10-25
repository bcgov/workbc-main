<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_unemployed_previous_month",
 *   label = @Translation("Unemployed Previous Month"),
 *   description = @Translation("An extra field to display industry unemployed previous month."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketUnemployedPreviousMonth extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Unemployed Previous Month');
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
    //month
    $monthNum = $entity->ssot_data['monthly_labour_market_updates'][0]['month'] - 1;

    if($monthNum == 0) {
      $monthNum = 12;
      $year = $year - 1;
    }

    $month = date ('F', mktime(0, 0, 0, $monthNum, 10));

    $total_unemployed = Number_format($entity->ssot_data['monthly_labour_market_updates'][0]['total_unemployed_previous']);
    $unemployed_rate_value =  $entity->ssot_data['monthly_labour_market_updates'][0]['employment_rate_pct_unemployment_previous']; 
    $unemployed_part_value = $entity->ssot_data['monthly_labour_market_updates'][0]['employment_rate_pct_participation_previous']; 
    $information_text_tooltip = '
                  <div class="tool-tip">
                    <p>'. $this-> ('Participation Rate represents the number of people in the workforce that are of working age as a percentage of total BC population.') . '</p>
                  </div>';  

    //output
    $output = '
    <div class="LME--total-unemployed">
    <span class="LME--total-unemployed-label">'.$this->t("Total Unemployed (@month @year)", ["@month" => $month, "@year" => $year]).'</span>
    <span class="LME--total-unemployed-value blue">'.$total_unemployed.'</span>
    <div class="LME--total-unemployed-rate">
      <div class="LME--total-unemployed-rate"><span>'.$this->t("Unemployment Rate").'</span><span class="LME--total-unemployed-rate-value">'.$unemployed_rate_value.'%</span></div>
      <div class="LME--total-unemployed-part">
        <span>'.$this->t("Participation Rate").'</span>
        <span class="LME--total-unemployed-part-value">'.$unemployed_part_value.'%</span>
        <span class="LME--total-unemployed-information-text">'.$information_text_tooltip.'</span>
      </div>
    </div>
    </div>';

    return [
      ['#markup' => $output],
    ];
  }

}
