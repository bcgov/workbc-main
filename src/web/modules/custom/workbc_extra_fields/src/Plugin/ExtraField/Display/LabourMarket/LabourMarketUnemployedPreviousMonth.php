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
 *   label = @Translation("[SSOT] Unemployed Previous Month"),
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

    if(empty($entity->ssot_data['monthly_labour_market_updates'])){
      $output = '<div>'. WORKBC_EXTRA_FIELDS_NOT_AVAILABLE .'</div>';
      return [
        ['#markup' => $output ],
      ];
    }

    $data = $entity->ssot_data['monthly_labour_market_updates'][0];
    $options = array(
      'decimals' => 0,
      'na_if_empty' => TRUE,
    );
    $options = array(
      'decimals' => 1,
      'suffix' => "%",
      'na_if_empty' => TRUE,
    );
    $total_unemployed = ssotFormatNumber($data['total_unemployed_previous'], $options);
    $current_previous_months = $entity->ssot_data['current_previous_months_names'];
    $unemployed_rate_value = ssotFormatNumber($data['employment_rate_pct_unemployment_previous'], $options);
    $unemployed_part_value = ssotFormatNumber($data['employment_rate_pct_participation_previous'], $options);
    $source_text = !empty($entity->ssot_data['sources']['no-datapoint'])?$entity->ssot_data['sources']['no-datapoint']:WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;

    $output = <<<EOS
    <div class="lm-data-box text-center">
      <div class="lm-label">
        {$this->t("<strong>Total Unemployed</strong> (@previousmonthyear)", ["@previousmonthyear" => $current_previous_months['previous_month_year']])}
      </div>
    <div class="lm-data-value">{$total_unemployed}</div>
    <div class="lm-data-container">
      <div class="lm-data-item">
        <div class="lm-data-item-label">{$this->t("Unemployment Rate")}</div><div class="lm-data-item-value">{$unemployed_rate_value}</div>
      </div>
      <div class="lm-data-item lm-has-tooltip">
        <div class="lm-data-item-label">{$this->t("Participation Rate")}</div>
        <div class="lm-data-item-value">
          {$unemployed_part_value}
          <span class="lm-tooltip">
            <a tabindex="0" id="lm-tooltip" class="btn btn-link info-tooltip info-tooltip-inline" role="button" data-bs-toggle="popover" data-bs-container="#lm-tooltip" data-bs-trigger="focus click hover" data-bs-placement="bottom" data-bs-custom-class="workbc-popover--inline" title="hidden title" data-bs-html="true" data-bs-content="{$this->t('Participation Rate represents the number of people in the workforce that are of working age as a percentage of total BC population.')}"></a>
          </span>
        </div>
      </div>
    </div>
    </div>
    <div class="lm-source"><strong>{$this->t("Source")}: </strong>{$source_text}</div>
  EOS;

    return [
      ['#markup' => $output],
    ];
  }

}
