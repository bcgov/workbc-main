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

    $options = array(
      'decimals' => 1,
      'suffix' => "%",
      'na_if_empty' => TRUE,
    );
    $regions = array();
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['labour_force_survey_industry'])) {

      $region = array();
      $region['name'] = t(REGION_CARIBOO);
      $region['industry'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_cariboo_employment_this_industry_pct'], $options);
      $region['all'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_cariboo_employment_all_industries_pct'], $options);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_KOOTENAY);
      $region['industry'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_kootenay_employment_this_industry_pct'], $options);
      $region['all'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_kootenay_employment_all_industries_pct'], $options);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_MAINLAND_SOUTHWEST);
      $region['industry'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_mainland_southwest_employment_this_industry_pct'], $options);
      $region['all'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_mainland_southwest_employment_all_industries_pct'], $options);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTH_COAST_NECHAKO);
      $region['industry'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_north_coast_nechako_employment_this_industry_pct'], $options);
      $region['all'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_north_coast_nechako_employment_all_industries_pct'], $options);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTHEAST);
      $region['industry'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_northeast_employment_this_industry_pct'], $options);
      $region['all'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_northeast_employment_all_industries_pct'], $options);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_THOMPSON_OKANAGAN);
      $region['industry'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_thompson_okanagan_employment_this_industry_pct'], $options);
      $region['all'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_thompson_okanagan_employment_all_industries_pct'], $options);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_VANCOUVER_ISLAND_COAST);
      $region['industry'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_vancouver_island_coast_employment_this_industry_pct'], $options);
      $region['all'] = ssotFormatNumber($entity->ssot_data['labour_force_survey_industry']['location_vancouver_island_coast_employment_all_industries_pct'], $options);
      $regions[] = $region;
    }

    $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'labour_force_survey_industry', 'total_employment');

    $header = ['Region', '% Employment this Industry (' . $datestr . ')', '% Employment All Industries (' . $datestr . ')'];

    $rows = [];
    foreach ($regions as $region) {
      $rows[] = [
        'data' => [$region['name'], $region['industry'], $region['all']], 
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
