<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_job_openings_composition_chart",
 *   label = @Translation("Job Openings Composition Chart"),
 *   description = @Translation("An extra field to display region job openings forecast chart."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionJobOpeningsCompositionChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Composition of Job Openings');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['regional_labour_market_outlook'])) {
      $data = array();
      $data[] = floatval($entity->ssot_data['regional_labour_market_outlook']['replacement_of_retiring_workers_openings']);
      $data[] = floatval($entity->ssot_data['regional_labour_market_outlook']['new_jobs_due_to_economic_growth_openings']);
      $chart = [
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
          '#labels' => [$this->t('Replacement of retiring workers'), $this->t('New jobs due to economic growth')],
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
        ],
        '#raw_options' => [
          'options' => [
            'pieHole' => 0.7,
            'height' => 350,
            'pieSliceText' => 'none',
            'legend' => [
              'labeledValueText' => 'both',
              'position' => 'labeled',
            ]
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
