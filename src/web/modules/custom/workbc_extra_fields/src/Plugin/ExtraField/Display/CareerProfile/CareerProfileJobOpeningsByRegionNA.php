<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "job_openings_by_region_na",
 *   label = @Translation("Labour Market Info - N/A: Job Openings by Region"),
 *   description = @Translation("An extra field to display job opening forecast chart N/A legend."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileJobOpeningsByRegionNA extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t("");
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
      $region['openings'] = floatval($entity->ssot_data['career_regional']['cariboo_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['cariboo_average_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['openings'] = floatval($entity->ssot_data['career_regional']['kootenay_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['kootenay_average_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['openings'] = floatval($entity->ssot_data['career_regional']['mainland_southwest_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['mainland_southwest_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['openings'] = floatval($entity->ssot_data['career_regional']['north_coast_nechako_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['north_coast_nechako_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['openings'] = floatval($entity->ssot_data['career_regional']['northeast_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['northeast_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['openings'] = floatval($entity->ssot_data['career_regional']['thompson_okanagan_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['thompson_okanagan_annual_employment_growth_10y_pct']);
      $regions[] = $region;
      $region = array();
      $region['openings'] = floatval($entity->ssot_data['career_regional']['vancouver_island_coast_expected_number_of_job_openings_10y']);
      $region['growth'] = floatval($entity->ssot_data['career_regional']['vancouver_island_coast_annual_employment_growth_10y_pct']);
      $regions[] = $region;
    }

    $has_null = false;
    foreach ($regions as $key => $value) {
      if (in_array(null, $value)) {
        $has_null = true;
        break;
      }
    }

    return [
      ['#markup' => $has_null ? "YES" : "NO"],
    ];
  }

}
