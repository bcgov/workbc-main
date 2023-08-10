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

    $options1 = array(
      'decimals' => 0,
      'no_negative' => TRUE,
      'na_if_empty' => TRUE,
    );
    $options2 = array(
      'decimals' => 1,
      'suffix' => "%",
      'na_if_empty' => TRUE,
    );    
    $regions = [];
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_regional'])) {
      $region = array();
      $region['name'] = t(REGION_CARIBOO);
      $region['openings'] = ssotFormatNumber($entity->ssot_data['career_regional']['cariboo_expected_number_of_job_openings_10y'], $options1);
      $region['growth'] = ssotFormatNumber($entity->ssot_data['career_regional']['cariboo_average_annual_employment_growth_10y_pct'], $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_KOOTENAY);
      $region['openings'] = ssotFormatNumber($entity->ssot_data['career_regional']['kootenay_expected_number_of_job_openings_10y'], $options1);
      $region['growth'] = ssotFormatNumber($entity->ssot_data['career_regional']['kootenay_average_annual_employment_growth_10y_pct'], $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_MAINLAND_SOUTHWEST);
      $region['openings'] = ssotFormatNumber($entity->ssot_data['career_regional']['mainland_southwest_expected_number_of_job_openings_10y'], $options1);
      $region['growth'] = ssotFormatNumber($entity->ssot_data['career_regional']['mainland_southwest_annual_employment_growth_10y_pct'], $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTH_COAST_NECHAKO);
      $region['openings'] = ssotFormatNumber($entity->ssot_data['career_regional']['north_coast_nechako_expected_number_of_job_openings_10y'], $options1);
      $region['growth'] = ssotFormatNumber($entity->ssot_data['career_regional']['north_coast_nechako_annual_employment_growth_10y_pct'], $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTHEAST);
      $region['openings'] = ssotFormatNumber($entity->ssot_data['career_regional']['northeast_expected_number_of_job_openings_10y'], $options1);
      $region['growth'] = ssotFormatNumber($entity->ssot_data['career_regional']['northeast_annual_employment_growth_10y_pct'], $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_THOMPSON_OKANAGAN);
      $region['openings'] = ssotFormatNumber($entity->ssot_data['career_regional']['thompson_okanagan_expected_number_of_job_openings_10y'], $options1);
      $region['growth'] = ssotFormatNumber($entity->ssot_data['career_regional']['thompson_okanagan_annual_employment_growth_10y_pct'], $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_VANCOUVER_ISLAND_COAST);
      $region['openings'] = ssotFormatNumber($entity->ssot_data['career_regional']['vancouver_island_coast_expected_number_of_job_openings_10y'], $options1);
      $region['growth'] = ssotFormatNumber($entity->ssot_data['career_regional']['vancouver_island_coast_annual_employment_growth_10y_pct'], $options2);
      $regions[] = $region;
    }

    $header = ['Region', 'Job Openings', 'Avg Annual Employment Growth'];

    $rows = [];
    foreach ($regions as $region) {
      $rows[] = [
        'data' => [$region['name'], $region['openings'], $region['growth']], 
        'class' => 'interactive-map-row-'.ssotRegionKey($region['name']),
      ];
    }

    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ); 
    return $table;

  }

}
