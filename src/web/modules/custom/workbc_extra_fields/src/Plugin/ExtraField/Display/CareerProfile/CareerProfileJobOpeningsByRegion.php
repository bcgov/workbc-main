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
    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'career_regional', 'cariboo_expected_number_of_job_openings_10y');
    return $this->t('Job Openings by Region (:datestr)', array(":datestr" => $datestr));
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
      $value = $entity->ssot_data['career_regional']['cariboo_expected_number_of_job_openings_10y'];
      $region['openings'] = ($value===0||$value) ? ssotFormatNumber(floatval($value),0) : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $value = $entity->ssot_data['career_regional']['cariboo_average_annual_employment_growth_10y_pct'];
      $region['growth'] = ($value===0||$value) ? ssotFormatNumber($value,1) . "%" : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_KOOTENAY);
      $value = $entity->ssot_data['career_regional']['kootenay_expected_number_of_job_openings_10y'];
      $region['openings'] = ($value===0||$value)  ? ssotFormatNumber(floatval($value),0) : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $value = $entity->ssot_data['career_regional']['kootenay_average_annual_employment_growth_10y_pct'];
      $region['growth'] = ($value===0||$value) ? ssotFormatNumber($value,1) . "%" : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_MAINLAND_SOUTHWEST);
      $value = $entity->ssot_data['career_regional']['mainland_southwest_expected_number_of_job_openings_10y'];
      $region['openings'] = ($value===0||$value) ? ssotFormatNumber(floatval($value),0) : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $value = $entity->ssot_data['career_regional']['mainland_southwest_annual_employment_growth_10y_pct'];
      $region['growth'] = ($value===0||$value) ? ssotFormatNumber($value,1) . "%" : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTH_COAST_NECHAKO);
      $value = $entity->ssot_data['career_regional']['north_coast_nechako_expected_number_of_job_openings_10y'];
      $region['openings'] = ($value===0||$value) ? ssotFormatNumber(floatval($value),0) : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $value = $entity->ssot_data['career_regional']['north_coast_nechako_annual_employment_growth_10y_pct'];
      $region['growth'] = ($value===0||$value) ? ssotFormatNumber($value,1) . "%" : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTHEAST);
      $value = $entity->ssot_data['career_regional']['northeast_expected_number_of_job_openings_10y'];
      $region['openings'] = ($value===0||$value) ? ssotFormatNumber(floatval($value),0) : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $value = $entity->ssot_data['career_regional']['northeast_annual_employment_growth_10y_pct'];
      $region['growth'] = ($value===0||$value) ? ssotFormatNumber($value,1) . "%" : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_THOMPSON_OKANAGAN);
      $value = $entity->ssot_data['career_regional']['thompson_okanagan_expected_number_of_job_openings_10y'];
      $region['openings'] = ($value===0||$value) ? ssotFormatNumber(floatval($value),0) : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $value = $entity->ssot_data['career_regional']['thompson_okanagan_annual_employment_growth_10y_pct'];
      $region['growth'] = ($value===0||$value) ? ssotFormatNumber($value,1) . "%" : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_VANCOUVER_ISLAND_COAST);
      $value = $entity->ssot_data['career_regional']['vancouver_island_coast_expected_number_of_job_openings_10y'];
      $region['openings'] = ($value===0||$value) ? ssotFormatNumber(floatval($value),0) : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $value = $entity->ssot_data['career_regional']['vancouver_island_coast_annual_employment_growth_10y_pct'];
      $region['growth'] = ($value===0||$value) ? ssotFormatNumber($value,1) . "%" : WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      $regions[] = $region;
    }

    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('workbc_extra_fields')->getPath();

    $text = '<div><img src="/' . $module_path . '/images/' . WORKBC_BC_MAP_WITH_LABELS . '"></div>';
    $text .= "<table>";
    $text .= "<tr><th>Region</th><th>Job Openings</th><th>Avg Annual Employment Growth</th></tr>";
    foreach ($regions as $region) {
      $text .= "<tr><td>" . $region['name'] . "</td><td>" . $region['openings'] . "</td><td>" . $region['growth'] . "</td></tr>";
    }
    $text .= "</table>";

    $output = $text;

    return [
      ['#markup' => $output],
    ];
  }

}
