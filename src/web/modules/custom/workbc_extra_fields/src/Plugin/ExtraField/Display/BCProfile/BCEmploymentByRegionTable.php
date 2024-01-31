<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\BCProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "bc_employment_by_region_table",
 *   label = @Translation("Employment by Region Table"),
 *   description = @Translation("An extra field to display employment by region table."),
 *   bundles = {
 *     "node.bc_profile",
 *   }
 * )
 */
class BCEmploymentByRegionTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Region');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_regions_employment']) && isset($entity->ssot_data['labour_force_survey_bc_employment'])) {
      $regions = $entity->ssot_data['labour_force_survey_regions_employment'];
      usort($regions, function($a, $b) {
        return $a['region'] <=> $b['region'];
      });

      $options = array(
        'decimals' => 0,
        'suffix' => '%',
        'na_if_empty' => TRUE,
      );

      $rows = array();
      foreach ($regions as $region) {
        $nid = \Drupal::entityQuery('node')
          ->condition('title', ssotRegionName($region['region']))
          ->sort('nid', 'DESC')
          ->execute();
        $nid = reset($nid);

        $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$nid);


        $rows[] = [
          'data' => [
            '<a href="' . $alias . '"> ' . ssotRegionName($region['region']) . '</a>',
            ssotFormatNumber($region['full_time_employment_pct'], $options),
            ssotFormatNumber($region['part_time_employment_pct'], $options),
          ],
          'class' => 'interactive-map-row-'. $region['region'],
        ];
      }

      $header = ['Region','Full-time Employment Rate', 'Part-time Employment Rate'];
      $footer = ['B.C. Average', ssotFormatNumber($entity->ssot_data['labour_force_survey_bc_employment']['full_time_employment_pct'], $options), ssotFormatNumber($entity->ssot_data['labour_force_survey_bc_employment']['part_time_employment_pct'], $options)];
      $table = array(
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#footer' => $footer,
      );
      $output = $table;
    }
    else {
      $output = ['#markup' => WORKBC_EXTRA_FIELDS_NOT_AVAILABLE];
    }
    return $output;
  }

}

