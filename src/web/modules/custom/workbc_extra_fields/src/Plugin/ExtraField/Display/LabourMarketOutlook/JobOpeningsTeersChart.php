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
 *   label = @Translation("Figure 2-1: Job Openings by TEER, B.C., 2024-2034"),
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

    $label = $this->getEntity()->getParentEntity()->ssot_data['schema']['definitions']['lmo_report_2024_job_openings_teers']['description'];
    return trim(explode('>', $label)[1]);
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
          '#title' => $this->t('Job Openings by TEER, B.C., 2024-2034'),
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
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
