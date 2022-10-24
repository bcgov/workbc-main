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

    //values
    $year = $entity->ssot_data['monthly_labour_market_updates'][0]['year'];
    //month
    $monthNum = $entity->ssot_data['monthly_labour_market_updates'][0]['month'];
    $month = date ('F', mktime(0, 0, 0, $monthNum, 10));

    $total_employment =  Number_format($entity->ssot_data['monthly_labour_market_updates'][0]['total_employed']);
    $source_text = $entity->ssot_data['sources']['no-datapoint'];

    //output
    $output = '
    <div class="LME--total-employed">
    <span class="LME--total-employed-label">'.$this->t("Total Employed (@month @year)", ["@month" => $month, "@year" => $year]).'</span>
    <span class="LME--total-employed-value blue">'.$total_employment.'</span>
    <span class="LME--total-employed-bottom-source"><strong>Source: </strong>'.$source_text.'</span>
    </div>';

    return [
      ['#markup' => $output ],
    ];
  }

}
