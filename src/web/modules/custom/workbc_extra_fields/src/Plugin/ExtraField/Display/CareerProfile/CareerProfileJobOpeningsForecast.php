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

    $chart = [
     '#type' => 'chart',
     '#chart_type' => 'column',
     'series' => [
       '#type' => 'chart_data',
       '#title' => t(''),
       '#data' => [510, 1960, 2120],
     ],
     'xaxis' => [
       '#type' => 'chart_xaxis',
       '#labels' => [t('2019'), t('2024'), t('2029')],
     ]
    ];
    $output = \Drupal::service('renderer')->render($chart);

    return [
      ['#markup' => $output],
    ];
  }

}
