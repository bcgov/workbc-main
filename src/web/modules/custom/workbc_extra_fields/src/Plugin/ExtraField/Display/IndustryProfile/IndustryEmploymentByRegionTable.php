<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_employment_by_region_table",
 *   label = @Translation("Employment by Region Table"),
 *   description = @Translation("An extra field to display industry employment by region table."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryEmploymentByRegionTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Region Table');
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

    $regions = array();
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_industry'])) {
      $region = array();
      $region['name'] = t(REGION_CARIBOO);
      $region['industry'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_cariboo_employment_this_industry_pct']);
      $region['all'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_cariboo_employment_all_industries_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_KOOTENAY);
      $region['industry'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_kootenay_employment_this_industry_pct']);
      $region['all'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_kootenay_employment_all_industries_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_MAINLAND_SOUTHWEST);
      $region['industry'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_mainland_southwest_employment_this_industry_pct']);
      $region['all'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_mainland_southwest_employment_all_industries_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTH_COAST_NECHAKO);
      $region['industry'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_north_coast_nechako_employment_this_industry_pct']);
      $region['all'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_north_coast_nechako_employment_all_industries_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTHEAST);
      $region['industry'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_northeast_employment_this_industry_pct']);
      $region['all'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_northeast_employment_all_industries_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_THOMPSON_OKANAGAN);
      $region['industry'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_thompson_okanagan_employment_this_industry_pct']);
      $region['all'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_thompson_okanagan_employment_all_industries_pct']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_VANCOUVER_ISLAND_COAST);
      $region['industry'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_vancouver_island_coast_employment_this_industry_pct']);
      $region['all'] = floatval($entity->ssot_data['labour_force_survey_industry']['location_vancouver_island_coast_employment_all_industries_pct']);
      $regions[] = $region;
    }


    $content = "<table>";
    $content .= "<tr><th>Region</th><th>Job Openings</th><th>Avg Annual Employment Growth</th></tr>";
    foreach ($regions as $region) {
      $content .= "<tr><td>" . $region['name'] . "</td><td>" . number_format($region['industry'], 1) . "%</td><td>" . number_format($region['all'],1) . "%</td></tr>";
    }
    $content .= "</table>";
    $output = $content;

    return [
      ['#markup' => $output],
    ];
  }

}
