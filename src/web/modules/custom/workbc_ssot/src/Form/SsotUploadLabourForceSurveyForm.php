<?php

namespace Drupal\workbc_ssot\Form;

/**
 * Upload form for the yearly Labour Force Survey (LFS) data sheet.
 *
 * The workbook has two tabs that feed four SSoT datasets:
 * - "Industry Profiles" -> labour_force_survey_industry
 * - "Regional Profiles" -> labour_force_survey_regional_employment,
 *                          labour_force_survey_regional_industry_region,
 *                          labour_force_survey_regional_industry_province
 *
 * @package Drupal\workbc_ssot\Form
 */
class SsotUploadLabourForceSurveyForm extends SsotUploadFormBase {

  protected string $id = 'labour_force_survey';
  protected string $title = 'Labour Force Survey';
  protected string $label = '{year} Labour Force Survey';
  protected ?string $author = 'BC Labour Market Information Office';
  protected string $event_type = 'labour_force_survey_update';

  // No workbc-ssot workflow handles this event yet.
  protected bool $repo_default = FALSE;

  // The sheet has no reliable period cell: trust the year selected in the form.
  protected array $period = ['granularity' => 'year'];

  protected array $toc = [
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/labour_force_survey_industry.load
    'labour_force_survey_industry' => [
      'sheet' => 'Industry Profiles',
      'shape' => 'table',
      'range' => 'A5:BC23',
      'write' => 'replace',
      // Vertically merged "B.C. average" cells: only the first row carries a value.
      'fill_down' => [
        'workforce_provincial_average_pct_men',
        'workforce_provincial_average_pct_women',
        'workforce_employment_under_25_pct_average',
        'workforce_employment_over_55_pct_average',
        'employment_part_time_pct_average',
        'employment_self_employment_pct_average',
        'employment_temporary_pct_average',
        'employment_firms_under_20_employees_pct_average',
        'employment_private_sector_pct_average',
        'employment_unemployment_rate_pct_average',
        'earnings_men_average',
        'earnings_women_average',
        'earnings_youth_average',
        'location_cariboo_employment_all_industries_pct',
        'location_kootenay_employment_all_industries_pct',
        'location_mainland_southwest_employment_all_industries_pct',
        'location_north_coast_nechako_employment_all_industries_pct',
        'location_northeast_employment_all_industries_pct',
        'location_thompson_okanagan_employment_all_industries_pct',
        'location_vancouver_island_coast_employment_all_industries_pct',
      ],
      'fields' => [
        'industry' => ['type' => 'industry', 'exceptions' => ['Total, All Industries']],
        'yoy_employment_growth_pct' => ['type' => 'chg_pct'],
        'yoy_change_employment' => ['type' => 'chg_abs'],
        'total_employment' => ['type' => 'abs'],
        'workforce_employment_gender_pct_men' => ['type' => 'pct'],
        'workforce_employment_gender_pct_women' => ['type' => 'pct'],
        'workforce_provincial_average_pct_men' => ['type' => 'pct'],
        'workforce_provincial_average_pct_women' => ['type' => 'pct'],
        'workforce_employment_under_25_pct_previous' => ['type' => 'pct'],
        'workforce_employment_under_25_pct' => ['type' => 'pct'],
        'workforce_employment_under_25_pct_average' => ['type' => 'pct'],
        'workforce_employment_over_55_pct_previous' => ['type' => 'pct'],
        'workforce_employment_over_55_pct' => ['type' => 'pct'],
        'workforce_employment_over_55_pct_average' => ['type' => 'pct'],
        'employment_part_time_pct_previous' => ['type' => 'pct'],
        'employment_part_time_pct' => ['type' => 'pct'],
        'employment_part_time_pct_average' => ['type' => 'pct'],
        'employment_self_employment_pct_previous' => ['type' => 'pct'],
        'employment_self_employment_pct' => ['type' => 'pct'],
        'employment_self_employment_pct_average' => ['type' => 'pct'],
        'employment_temporary_pct_previous' => ['type' => 'pct'],
        'employment_temporary_pct' => ['type' => 'pct'],
        'employment_temporary_pct_average' => ['type' => 'pct'],
        'employment_firms_under_20_employees_pct_previous' => ['type' => 'pct'],
        'employment_firms_under_20_employees_pct' => ['type' => 'pct'],
        'employment_firms_under_20_employees_pct_average' => ['type' => 'pct'],
        'employment_private_sector_pct_previous' => ['type' => 'pct'],
        'employment_private_sector_pct' => ['type' => 'pct'],
        'employment_private_sector_pct_average' => ['type' => 'pct'],
        'employment_unemployment_rate_pct_previous' => ['type' => 'pct'],
        'employment_unemployment_rate_pct' => ['type' => 'pct'],
        'employment_unemployment_rate_pct_average' => ['type' => 'pct'],
        'earnings_men_previous' => ['type' => 'num'],
        'earnings_men' => ['type' => 'num'],
        'earnings_men_average' => ['type' => 'num'],
        'earnings_women_previous' => ['type' => 'num'],
        'earnings_women' => ['type' => 'num'],
        'earnings_women_average' => ['type' => 'num'],
        'earnings_youth_previous' => ['type' => 'num'],
        'earnings_youth' => ['type' => 'num'],
        'earnings_youth_average' => ['type' => 'num'],
        'location_cariboo_employment_this_industry_pct' => ['type' => 'pct'],
        'location_cariboo_employment_all_industries_pct' => ['type' => 'pct'],
        'location_kootenay_employment_this_industry_pct' => ['type' => 'pct'],
        'location_kootenay_employment_all_industries_pct' => ['type' => 'pct'],
        'location_mainland_southwest_employment_this_industry_pct' => ['type' => 'pct'],
        'location_mainland_southwest_employment_all_industries_pct' => ['type' => 'pct'],
        'location_north_coast_nechako_employment_this_industry_pct' => ['type' => 'pct'],
        'location_north_coast_nechako_employment_all_industries_pct' => ['type' => 'pct'],
        'location_northeast_employment_this_industry_pct' => ['type' => 'pct'],
        'location_northeast_employment_all_industries_pct' => ['type' => 'pct'],
        'location_thompson_okanagan_employment_this_industry_pct' => ['type' => 'pct'],
        'location_thompson_okanagan_employment_all_industries_pct' => ['type' => 'pct'],
        'location_vancouver_island_coast_employment_this_industry_pct' => ['type' => 'pct'],
        'location_vancouver_island_coast_employment_all_industries_pct' => ['type' => 'pct'],
      ],
    ],
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/labour_force_survey_regional_employment.load
    'labour_force_survey_regional_employment' => [
      'sheet' => 'Regional Profiles',
      'shape' => 'table',
      'range' => 'A6:V13',
      'write' => 'replace',
      'fields' => [
        'region' => ['type' => 'region'],
        'age_distribution_0_14' => ['type' => 'pct'],
        'age_distribution_15_24' => ['type' => 'pct'],
        'age_distribution_25_54' => ['type' => 'pct'],
        'age_distribution_55_64' => ['type' => 'pct'],
        'age_distribution_65' => ['type' => 'pct'],
        'full_time_employment_pct' => ['type' => 'pct'],
        'full_time_employment_num' => ['type' => 'abs'],
        'unemployment_rate_year_1' => ['type' => 'pct'],
        'unemployment_rate_year_2' => ['type' => 'pct'],
        'unemployment_rate_year_3' => ['type' => 'pct'],
        'unemployment_rate_year_4' => ['type' => 'pct'],
        'unemployment_rate_year_5' => ['type' => 'pct'],
        'unemployment_rate_year_6' => ['type' => 'pct'],
        'unemployment_rate_year_7' => ['type' => 'pct'],
        'unemployment_rate_year_8' => ['type' => 'pct'],
        'unemployment_rate_year_9' => ['type' => 'pct'],
        'unemployment_rate_year_10' => ['type' => 'pct'],
        'unemployment_rate_year_11' => ['type' => 'pct'],
        'unemployment_rate_year_low' => ['type' => 'pct'],
        'unemployment_rate_year_high' => ['type' => 'pct'],
        'unemployment_rate_year_avg' => ['type' => 'pct'],
      ],
    ],
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/labour_force_survey_regional_industry_region.load
    'labour_force_survey_regional_industry_region' => [
      'sheet' => 'Regional Profiles',
      'shape' => 'table',
      'range' => 'A21:AK28',
      'write' => 'replace',
      'fields' => [
        'region' => ['type' => 'region'],
        'accommodation_food_services_num' => ['type' => 'abs'],
        'accommodation_food_services' => ['type' => 'pct'],
        'agriculture_fishing_num' => ['type' => 'abs'],
        'agriculture_fishing' => ['type' => 'pct'],
        'business_building_other_support_services_num' => ['type' => 'abs'],
        'business_building_other_support_services' => ['type' => 'pct'],
        'construction_num' => ['type' => 'abs'],
        'construction' => ['type' => 'pct'],
        'educational_services_num' => ['type' => 'abs'],
        'educational_services' => ['type' => 'pct'],
        'finance_insurance_real_estate_num' => ['type' => 'abs'],
        'finance_insurance_real_estate' => ['type' => 'pct'],
        'forestry_logging_support_activities_num' => ['type' => 'abs'],
        'forestry_logging_support_activities' => ['type' => 'pct'],
        'health_care_social_assistance_num' => ['type' => 'abs'],
        'health_care_social_assistance' => ['type' => 'pct'],
        'information_culture_recreation_num' => ['type' => 'abs'],
        'information_culture_recreation' => ['type' => 'pct'],
        'manufacturing_num' => ['type' => 'abs'],
        'manufacturing' => ['type' => 'pct'],
        'mining_oil_gas_extraction_num' => ['type' => 'abs'],
        'mining_oil_gas_extraction' => ['type' => 'pct'],
        'professional_scientific_technical_services_num' => ['type' => 'abs'],
        'professional_scientific_technical_services' => ['type' => 'pct'],
        'public_administration_num' => ['type' => 'abs'],
        'public_administration' => ['type' => 'pct'],
        'other_private_services_num' => ['type' => 'abs'],
        'other_private_services' => ['type' => 'pct'],
        'retail_trade_num' => ['type' => 'abs'],
        'retail_trade' => ['type' => 'pct'],
        'transportation_warehousing_num' => ['type' => 'abs'],
        'transportation_warehousing' => ['type' => 'pct'],
        'utilities_num' => ['type' => 'abs'],
        'utilities' => ['type' => 'pct'],
        'wholesale_trade_num' => ['type' => 'abs'],
        'wholesale_trade' => ['type' => 'pct'],
      ],
    ],
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/labour_force_survey_regional_industry_province.load
    'labour_force_survey_regional_industry_province' => [
      'sheet' => 'Regional Profiles',
      'shape' => 'table',
      'range' => 'A35:U42',
      'write' => 'replace',
      'fields' => [
        'region' => ['type' => 'region'],
        'accommodation_food_services' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'agriculture_fishing' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'business_building_other_support_services' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'construction' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'educational_services' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'finance_insurance_real_estate' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'forestry_logging_support_activities' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'health_care_social_assistance' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'information_culture_recreation' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'manufacturing' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'mining_oil_gas_extraction' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'professional_scientific_technical_services' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'public_administration' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'other_private_services' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'retail_trade' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'transportation_warehousing' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'utilities' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'wholesale_trade' => ['type' => 'pct', 'blank_if' => ['field' => 'region', 'value' => 'british_columbia']],
        'goods' => ['type' => 'pct'],
        'services' => ['type' => 'pct'],
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function getRouteName(): string {
    return 'workbc_ssot.lfs';
  }
}
