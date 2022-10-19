<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_by_sex",
 *   label = @Translation("Employment by Sex"),
 *   description = @Translation("An extra field to display industry employment by sex."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentBySex extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Sex');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_industry'])) {
      $data = array();
      $data[] = floatval($entity->ssot_data['labour_force_survey_industry']['workforce_employment_gender_2020_pct_men']);
      $data[] = floatval($entity->ssot_data['labour_force_survey_industry']['workforce_employment_gender_2020_pct_women']);
      $labels = [t('Men'), t('Women')];
      $chart = [
        '#type' => 'chart',
        '#chart_type' => 'donut',
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
