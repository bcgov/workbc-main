<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_10y_chart",
 *   label = @Translation("Job Openings, B.C. (2024-2034)"),
 *   description = @Translation("An extra field to display job openings chart."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class BCJobOpeningsCompositionChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['label'];
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

    $entity = $paragraph->getParentEntity();
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['lmo_report_2024_job_openings_10y'])) {
      $data = [
        floatval(array_find($entity->ssot_data['lmo_report_2024_job_openings_10y'], function($entry) {
          return $entry['key'] === 'expansion';
        })['amount']),
        floatval(array_find($entity->ssot_data['lmo_report_2024_job_openings_10y'], function($entry) {
          return $entry['key'] === 'replacement';
        })['amount'])
      ];
      $chart = [
        '#chart_id' => 'lmo_report_2024_job_openings_10y_chart',
        '#type' => 'chart',
        '#chart_type' => 'donut',
        '#colors' => array(
          '#009cde',
          '#002857'
        ),
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Composition of Job Openings'),
          '#data' => $data,
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => [$this->t('Expansion'), $this->t('Replacement')],
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
        ],
        '#legend_position' => 'right',
        '#raw_options' => [
          'options' => [
            'chartArea' => [
              'left' => '10%',
              'top' => '10%',
              'width' => '80%',
              'height' => '80%',
            ],
            'pieHole' => 0.7,
            'height' => 300,
            'pieSliceText' => 'none',
          ]
        ]
      ];
      $output = \Drupal::service('renderer')->render($chart);
      $source_text = $entity->ssot_data['sources']['label'] ?? WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $output .= <<<END
      <div class="lm-source"><strong>Source:</strong>&nbsp;$source_text</div>
      END;
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
