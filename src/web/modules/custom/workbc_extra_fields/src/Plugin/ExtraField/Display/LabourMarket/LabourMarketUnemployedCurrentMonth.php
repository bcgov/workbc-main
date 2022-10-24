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

    //values
    // print $entity->ssot_data['monthly_labour_market_updates'][0]['month']; exit;
    $year = $entity->ssot_data['monthly_labour_market_updates'][0]['year'];
    //month
    $monthNum = $entity->ssot_data['monthly_labour_market_updates'][0]['month'];
    $month = date ('F', mktime(0, 0, 0, $monthNum, 10));

    $total_unemployed = Number_format($entity->ssot_data['monthly_labour_market_updates'][0]['total_unemployed']);
    $unemployed_rate_value = $entity->ssot_data['monthly_labour_market_updates'][0]['employment_rate_pct_unemployment']; 
    $unemployed_part_value = $entity->ssot_data['monthly_labour_market_updates'][0]['employment_rate_pct_participation']; 
    $source_text = $entity->ssot_data['sources']['no-datapoint'];

    //output
    $output = '
    <div class="LME--total-unemployed">
    <span class="LME--total-unemployed-label">'.$this->t("Total Unemployed (@month @year)", ["@month" => $month, "@year" => $year]).'</span>
    <span class="LME--total-unemployed-value blue">'.$total_unemployed.'</span>
    <div class="LME--total-unemployed-rate">
      <div class="LME--total-unemployed-rate"><span>'.$this->t("Unemployment Rate").'</span><span class="LME--total-unemployed-rate-value">'.$unemployed_rate_value.'%</span></div>
      <div class="LME--total-unemployed-part"><span>'.$this->t("Participation Rate").'</span><span class="LME--total-unemployed-part-value">'.$unemployed_part_value.'%</span></div>
    </div>
    <span class="LME--total-employed-bottom-source"><strong>Source: </strong>'.$source_text.'</span>
    </div>';

    return [
      ['#markup' => $output],
    ];
  }

}
