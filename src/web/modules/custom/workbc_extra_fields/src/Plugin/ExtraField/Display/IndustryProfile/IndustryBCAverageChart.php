<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_bc_average_chart",
 *   label = @Translation("BC Average Chart"),
 *   description = @Translation("An extra field to display industry bc average chart."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryBCAverageChart extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_industry', 'workforce_provincial_average_pct_men');
    return $this->t("BC Average (" . $datestr . ")");
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
      $data[] = floatval($entity->ssot_data['labour_force_survey_industry']['workforce_provincial_average_pct_men']);
      $data[] = floatval($entity->ssot_data['labour_force_survey_industry']['workforce_provincial_average_pct_women']);
      $labels = [$this->t('Men'), $this->t('Women')];
      $chart = [
        '#chart_id' => 'industry-bc-average',
        '#type' => 'chart',
        '#chart_type' => 'donut',
        'series' => [
          '#type' => 'chart_data',
          '#title' => $this->t('BC Average'),
          '#data' => $data,
        ],
        'series_tooltip' => [
          '#type' => 'chart_data',
          '#title' => ['role' => 'tooltip'],
          '#data' => array_map(function($v, $l) {
            $options = array(
              'decimals' => 1,
              'suffix' => "%",
              'na_if_empty' => TRUE,
            );
            return $l .' ' . ssotFormatNumber($v, $options);
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
