<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_employment",
 *   label = @Translation("Employment"),
 *   description = @Translation("An extra field to display industry employment."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketEmployment extends ExtraFieldDisplayFormattedBase {

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

    if(empty($entity->ssot_data['monthly_labour_market_updates'])) {
      $output = '<div>'. WORKBC_EXTRA_FIELDS_NOT_AVAILABLE .'</div>';
      return [
        ['#markup' => $output ],
      ];
    }

    $current_previous_months = !empty($entity->ssot_data['current_previous_months_names'])?$entity->ssot_data['current_previous_months_names']:WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $total_employment =  !empty($entity->ssot_data['monthly_labour_market_updates'][0]['total_employed'])?ssotFormatNumber($entity->ssot_data['monthly_labour_market_updates'][0]['total_employed']):WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $source_text = !empty($entity->ssot_data['sources']['no-datapoint'])?$entity->ssot_data['sources']['no-datapoint']:WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;

    //output
    $output = '
    <div class="LME--total-employed">
    <span class="LME--total-employed-label">'.$this->t("Total Employed (@currentmonthyear)", ["@currentmonthyear" => $current_previous_months['current_month_year']]).'</span>
    <span class="LME--total-employed-value blue">'.$total_employment.'</span>
    <span class="LME--total-employed-bottom-source"><strong>'.$this->t("Source: ").'</strong>'.$source_text.'</span>
    </div>';

    return [
      ['#markup' => $output ],
    ];
  }

}
