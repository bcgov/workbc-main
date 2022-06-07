<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "employment_growth_rate_forecast",
 *   label = @Translation("Labour Market Info - Forecasted Employment Growth Rate"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileGrowthRateForecast extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Forecasted Employment Growth Rate');
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
       '#data' => [1.2, 1.5],
     ],
     'xaxis' => [
       '#type' => 'chart_xaxis',
       '#labels' => [t('2019-2024'), t('2024-2029')],
     ]
    ];
    $output = \Drupal::service('renderer')->render($chart);

    return [
      ['#markup' => $output],
    ];
  }

}
