<?php

namespace Drupal\workbc_ssot\Plugin\views\field;

use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\Date;
use Drupal\views\ResultRow;

/**
 * Displays yearly upload periods without a month.
 */
#[ViewsField('workbc_ssot_dataset_period')]
class SsotDatasetPeriod extends Date {

  private const YEARLY_DATASETS = [
    'industry_outlook',
    'labour_force_survey_industry',
    'labour_force_survey_regional_employment',
    'labour_force_survey_regional_industry_region',
    'labour_force_survey_regional_industry_province',
  ];

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $datasets = array_filter(array_map('trim', explode(',', (string) $this->getValue($values, 'dataset_name'))), 'strlen');
    $value = $this->getValue($values);
    if ($value && $datasets && !array_diff($datasets, self::YEARLY_DATASETS)) {
      return $this->dateFormatter->format($value, 'custom', 'Y', $this->options['timezone'] ?: NULL);
    }
    return parent::render($values);
  }

}
