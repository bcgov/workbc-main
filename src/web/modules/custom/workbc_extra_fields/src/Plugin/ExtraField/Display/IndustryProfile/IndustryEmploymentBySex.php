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
    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_industry', 'workforce_employment_gender_pct_men');
    return $this->t("Employment by Sex (" . $datestr . ")");
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
      $data[] = floatval($entity->ssot_data['labour_force_survey_industry']['workforce_employment_gender_pct_men']);
      $data[] = floatval($entity->ssot_data['labour_force_survey_industry']['workforce_employment_gender_pct_women']);
      $labels = [$this->t('Men'), $this->t('Women')];
      $chart = [
        '#chart_id' => 'industry-employment-by-sex',
        '#type' => 'chart',
        '#chart_type' => 'donut',
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('Employment by Sex'),
          '#data' => $data,
        ],
        'series_tooltip' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip'],
          '#data' => array_map(function($v, $l) {
            $options = array(
              'decimals' => 1,
              'suffix' => "%",
            );
            return $l . ' ' . ssotFormatNumber($v, $options);
          }, $data, $labels),
        ],
        'xaxis' => [
          '#type' => 'chart_xaxis',
          '#labels' => $labels,
        ],
        'yaxis' => [
          '#type' => 'chart_yaxis',
        ],
        '#raw_options' => [
          'options' => [
            'pieHole' => 0.7,
          ]
        ]
      ];
      $output = \Drupal::service('renderer')->render($chart);

      $md = \Drupal::service('mobile_detect');
      $isMobile = $md->isMobile() && !$md->isTablet();
      if ($isMobile) {
        $legend = "<div class='card-profile__legend-container'>";  // margin: auto;
        $legend .= "<div class='card-profile__legend'>";
        $legend .= "<div class='card-profile__legend-label card-profile__legend-label--men'>Men</div>";
        $legend .= "<div class='card-profile__legend-label card-profile__legend-label--women'>Women</div>";
        $legend .= "</div>";
        $legend .= "</div>";
        $output .= $legend;
      }
    }
    else {
      $output = "";
    }

    return [
      ['#markup' => $output],
    ];
  }

}
