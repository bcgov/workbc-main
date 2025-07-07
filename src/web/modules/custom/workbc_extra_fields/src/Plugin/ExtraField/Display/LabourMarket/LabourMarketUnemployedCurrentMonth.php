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
 *   label = @Translation("[SSOT] Unemployed Current Month"),
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

    $options = array(
      'decimals' => 0,
      'na_if_empty' => TRUE,
    );
    //values
    $current_previous_months = $entity->ssot_data['current_previous_months_names'];
    $total_unemployed = ssotFormatNumber($data['total_unemployed'], $options);

    $options = array(
      'decimals' => 1,
      'suffix' => "%",
      'na_if_empty' => TRUE,
    );
    $unemployed_rate_value = ssotFormatNumber($data['employment_rate_pct_unemployment'], $options);
    $unemployed_part_value = ssotFormatNumber($data['employment_rate_pct_participation'], $options);

    //output
    $output = '
    <div class="lm-data-box text-center">
    <div class="lm-label">'.$this->t("<strong>Total Unemployed</strong> (@currentmonthyear)", ['@currentmonthyear' => $current_previous_months['current_month_year']]).'</div>
    <div class="lm-data-value">'.$total_unemployed.'</div>
    <div class="lm-data-container">
      <div class="lm-data-item"><div class="lm-data-item-label">'.$this->t("Unemployment Rate").'</div><div class="lm-data-item-value">'.$unemployed_rate_value.'</div></div>
      <div class="lm-data-item"><div class="lm-data-item-label">'.$this->t("Participation Rate").'</div><div class="lm-data-item-value">'.$unemployed_part_value.'</div></div>
    </div>
    </div>';

    return [
      ['#markup' => $output],
    ];
  }

}
