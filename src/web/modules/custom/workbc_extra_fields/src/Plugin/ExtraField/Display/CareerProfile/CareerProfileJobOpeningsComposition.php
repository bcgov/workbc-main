<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "job_openings_composition",
 *   label = @Translation("[SSOT] Labour Market Info - Composition of Job Openings"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileJobOpeningsComposition extends ExtraFieldDisplayFormattedBase {

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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_provincial']) &&
        !is_null($entity->ssot_data['career_provincial']['replacement_of_retiring_workers_10y_pct']) &&
        !is_null($entity->ssot_data['career_provincial']['new_jobs_due_to_economic_growth_10y_pct'])) {
      $data = array();
      $data[] = floatval($entity->ssot_data['career_provincial']['replacement_of_retiring_workers_10y_pct']);
      $data[] = floatval($entity->ssot_data['career_provincial']['new_jobs_due_to_economic_growth_10y_pct']);
      $chart = [
        '#chart_id' => 'career-composition-job-openings',
        '#type' => 'chart',
        '#chart_type' => 'donut',
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
          ],
        ]
      ];
      $output = \Drupal::service('renderer')->render($chart);
    }
    else {
      $output = '<div class="workbc-data-not-available-350">' . WORKBC_EXTRA_FIELDS_DATA_NOT_AVAILABLE . "</div>";
    }

    return [
      ['#markup' => $output],
    ];
  }

}
