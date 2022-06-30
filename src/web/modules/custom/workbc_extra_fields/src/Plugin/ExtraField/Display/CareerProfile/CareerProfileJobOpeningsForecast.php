<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "job_openings_forecast",
 *   label = @Translation("Labour Market Info - Forecasted Job Openings"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileJobOpeningsForecast extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Forecasted Job Openings');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_provincial'])) {
      $data = array();
      $data[] = intval($entity->ssot_data['career_provincial']['job_openings_2022']);
      $data[] = intval($entity->ssot_data['career_provincial']['job_openings_2026']);
      $data[] = intval($entity->ssot_data['career_provincial']['job_openings_2031']);
      $labels = [t('2022'), t('2026'), t('2031')];
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'column',
        'series' => [
          '#type' => 'chart_data',
          '#title' => t(''),
          '#data' => $data,
          '#prefix' => '',
          '#suffix' => '',
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => $labels,
          '#max' => count($data),
          '#min' => 0,
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
          '#max' => max($data),
          '#min' => 0,
        ]
      ];
      $output = \Drupal::service('renderer')->render($chart);
    }
    else {
      $output = "";
    }

    return [
      ['#markup' => $output],
    ];
  }

}
