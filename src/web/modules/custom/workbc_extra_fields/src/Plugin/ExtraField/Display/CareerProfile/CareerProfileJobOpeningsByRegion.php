<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "job_openings_by_region",
 *   label = @Translation("Labour Market Info - Job Openings by Region"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileJobOpeningsByRegion extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Job Openings');
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

    $regions = [];
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_regional'])) {
      $region = array();
      $region['name'] = t(REGION_CARIBOO);
      $region['openings'] = floatval($entity->ssot_data['career_regional']['cariboo_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['cariboo_average_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_KOOTENAY);
      $region['openings'] = floatval($entity->ssot_data['career_regional']['kootenay_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['kootenay_average_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_MAINLAND_SOUTHWEST);
      $region['openings'] = floatval($entity->ssot_data['career_regional']['mainland_southwest_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['mainland_southwest_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTH_COAST_NECHAKO);
      $region['openings'] = floatval($entity->ssot_data['career_regional']['north_coast_and_nechako_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['north_coast_and_nechako_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTHEAST);
      $region['openings'] = floatval($entity->ssot_data['career_regional']['northeast_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['northeast_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_THOMPSON_OKANAGAN);
      $region['openings'] = floatval($entity->ssot_data['career_regional']['thompson_okanagan_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['thompson_okanagan_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_VANCOUVER_ISLAND_COAST);
      $region['openings'] = floatval($entity->ssot_data['career_regional']['vancouver_island_coast_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['vancouver_island_coast_annual_employment_growth_10y_pct']);
      $regions[] = $region;
    }



    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('workbc_extra_fields')->getPath();

    $text = '<div><img src="/' . $module_path . '/images/' . WORKBC_BC_MAP_WITH_LABELS . '"></div>';
    $text .= "<table>";
    $text .= "<tr><th>Region</th><th>Job Openings</th><th>Avg Annual Employment Growth</th></tr>";
    foreach ($regions as $region) {
      $text .= "<tr><td>" . $region['name'] . "</td><td>" . number_format($region['openings']) . "</td><td>" . number_format($region['growth'],1) . "%</td></tr>";
    }
    $text .= "</table>";
    $output = $text;

    return [
      ['#markup' => $output],
    ];
  }

}
