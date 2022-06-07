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
 *   label = @Translation("Labour Market Info - Composition of Job Openings"),
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

    $chart = [
     '#type' => 'chart',
     '#chart_type' => 'donut',
     'series' => [
       '#type' => 'chart_data',
       '#title' => t(''),
       '#data' => [12230, 7360],
     ],
     'xaxis' => [
       '#type' => 'chart_xaxis',
       '#labels' => [t('Replacement'), t('New Jobs')],
     ]
    ];
    $output = \Drupal::service('renderer')->render($chart);

    return [
      ['#markup' => $output],
    ];
  }

}
