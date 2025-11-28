<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

class LabourMarketOutLookExtraFieldBase extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  protected $report;
  protected $year;
  protected $dataset;
  protected $datestr;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return str_replace('[SSOT] ', '', $this->pluginDefinition['label']) . ($this->datestr ? " ($this->datestr)" : '');
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
  public function viewElements(ContentEntityInterface $paragraph) {
    // Don't display if this field is not selected in the parent paragraph.
    if ($this->getPluginId() != $paragraph->get('field_lmo_charts_tables')->value) {
      return null;
    }

    // Get the report node and make sure the data is set.
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $this->report = $paragraph->getParentEntity();
    $this->year = $this->report->field_report_year->value ?? '2024';
    if (empty($this->report->ssot_data) || empty($this->report->ssot_data[$this->getDataset()])) {
      return [
        ['#markup' => WORKBC_EXTRA_FIELDS_NOT_AVAILABLE],
      ];
    }

    // Set the label's date range.
    $this->datestr = ssotParseDateRange($this->report->ssot_data['schema'], $this->getDataset(true));

    // Call the child function's display.
    return [
      ['#markup' => $this->viewDatasetElement()],
    ];
  }

  protected function viewDatasetElement() { return WORKBC_EXTRA_FIELDS_NOT_AVAILABLE; }

  protected function getDataset($includeYear = false) {
    return str_replace(['_table', '_chart'], '', str_replace('_2024', $includeYear ? "_{$this->year}" : '', $this->getPluginId()));
  }
}
