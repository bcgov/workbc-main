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
      $entity->ssot_data['career_provincial']['job_openings_first'] = !isset($entity->ssot_data['career_provincial']['job_openings_first']) ? 0 : $entity->ssot_data['career_provincial']['job_openings_first'];
      $entity->ssot_data['career_provincial']['job_openings_second'] = !isset($entity->ssot_data['career_provincial']['job_openings_second']) ? 0 : $entity->ssot_data['career_provincial']['job_openings_second'];
      $entity->ssot_data['career_provincial']['job_openings_third'] = !isset($entity->ssot_data['career_provincial']['job_openings_third']) ? 0 : $entity->ssot_data['career_provincial']['job_openings_third'];
      $data = array();
      $value = intval($entity->ssot_data['career_provincial']['job_openings_first']);
      $data[] = $value < 0 ? 0 : $value;
      $value = intval($entity->ssot_data['career_provincial']['job_openings_second']);
      $data[] = $value < 0 ? 0 : $value;
      $value = intval($entity->ssot_data['career_provincial']['job_openings_third']);
      $data[] = $value < 0 ? 0 : $value;
      $dates = array();
      $dates[] = ssotParseDateRange($entity->ssot_data['schema'], 'career_provincial', 'job_openings_first');
      $dates[] = ssotParseDateRange($entity->ssot_data['schema'], 'career_provincial', 'job_openings_second');
      $dates[] = ssotParseDateRange($entity->ssot_data['schema'], 'career_provincial', 'job_openings_third');
      $chart = [
        '#chart_id' => "career-forecasted-job-openings",
        '#type' => 'chart',
        '#chart_type' => 'column',
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Forecasted Job Openings'),
          '#data' => $data,
        ],
        'series_annotation' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'annotation'],
          '#data' => array_map(function($v) {
            $options = array(
              'decimals' => 0,
              'positive_sign' => TRUE,
              'na_if_empty' => TRUE,
            );
            return ssotFormatNumber($v, $options);
          }, $data),
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => $dates,
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
          '#raw_options' => [
            'textPosition' => 'none',
            'gridlines' => [
              'count' => 1,
            ],
            'minValue' => 0,
          ]
        ],
        '#legend_position' => 'none',
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
