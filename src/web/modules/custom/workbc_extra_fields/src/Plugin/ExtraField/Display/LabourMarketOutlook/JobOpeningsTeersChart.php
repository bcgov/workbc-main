<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarketOutlook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "lmo_report_2024_job_openings_teers_chart",
 *   label = @Translation("Job Openings by TEER (2024-2034)"),
 *   description = @Translation("An extra field to display job openings chart."),
 *   bundles = {
 *     "paragraph.lmo_charts_tables",
 *   }
 * )
 */
class JobOpeningsTeersChart extends ExtraFieldDisplayFormattedBase {

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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['lmo_report_2024_job_openings_teers'])) {
      $data = array_map(function($entry) {
        return $entry['openings_rounded'];
      }, array_filter($entity->ssot_data['lmo_report_2024_job_openings_teers'], function ($entry) {
        return $entry['teer'] !== 'Total';
      }));
      $chart = [
        '#chart_id' => 'lmo_report_2024_job_openings_teers_chart',
        '#type' => 'chart',
        '#chart_type' => 'donut',
        '#colors' => array(
          '#70ad47',
          '#5b9bd5',
          '#ffc000',
          '#43682b',
          '#255e91',
          '#997300'
        ),
        'series' => [
          '#type' => 'chart_data',
          '#data' => $data,
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => array_map(function($entry) {
            return $entry['teer'];
          }, array_filter($entity->ssot_data['lmo_report_2024_job_openings_teers'], function ($entry) {
            return $entry['teer'] !== 'Total';
          })),
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
        ],
        '#raw_options' => [
          'options' => [
            'pieHole' => 0.7,
            'height' => 350,
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
