<?php

namespace Drupal\workbc_ssot\Form;

/**
 * Upload form for the Labour Market Monthly Update (LMMU) spreadsheet.
 *
 * @package Drupal\workbc_ssot\Form
 */
class SsotUploadLmmuForm extends SsotUploadFormBase {

  protected string $id = 'monthly_labour_market_updates';
  protected string $title = 'Labour Market Monthly Update';
  protected string $label = 'Labour Force Survey (monthly, seasonally adjusted)';
  protected string $event_type = 'monthly_labour_market_update';
  protected ?string $frontend_path = '/research-labour-market/bcs-economy/labour-market-monthly-update';

  protected array $period = [
    'granularity' => 'month',
    'check' => ['sheet' => 'Sheet3', 'cell' => 'A3', 'format' => 'excel_date'],
  ];

  // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/updates/monthly_labour_market_updates.load
  protected array $toc = [
    'monthly_labour_market_updates' => [
      'sheet' => 'Sheet3',
      'shape' => 'record',
      'write' => ['mode' => 'upsert', 'match' => 'year=eq.{year}&month=eq.{month}'],
      'crosscheck' => 'monthly_labour_market_updates?year=eq.{prev_year}&month=eq.{prev_month}',
      'fields' => [
        'month' => ['value' => '{month}'],
        'year' => ['value' => '{year}'],

        'employment_by_age_group_15_24' => ['cell' => 'C8', 'type' => 'abs'],
        'employment_by_age_group_25_54' => ['cell' => 'C9', 'type' => 'abs'],
        'employment_by_age_group_55' => ['cell' => 'C10', 'type' => 'abs'],
        'employment_by_age_group_15_24_previous' => ['cell' => 'B8', 'type' => 'abs', 'crosscheck_value' => 'employment_by_age_group_15_24'],
        'employment_by_age_group_25_54_previous' => ['cell' => 'B9', 'type' => 'abs', 'crosscheck_value' => 'employment_by_age_group_25_54'],
        'employment_by_age_group_55_previous' => ['cell' => 'B10', 'type' => 'abs', 'crosscheck_value' => 'employment_by_age_group_55'],

        'employment_by_gender_women' => ['cell' => 'C12', 'type' => 'abs'],
        'employment_by_gender_men' => ['cell' => 'C13', 'type' => 'abs'],
        'employment_by_gender_women_previous' => ['cell' => 'B12', 'type' => 'abs', 'crosscheck_value' => 'employment_by_gender_women'],
        'employment_by_gender_men_previous' => ['cell' => 'B13', 'type' => 'abs', 'crosscheck_value' => 'employment_by_gender_men'],

        'total_unemployed' => ['cell' => 'B37', 'type' => 'abs'],
        'total_unemployed_previous' => ['cell' => 'A37', 'type' => 'abs', 'crosscheck_value' => 'total_unemployed'],
        'total_employed' => ['cell' => 'B3', 'type' => 'abs', 'sum' => [
          ['employment_by_age_group_15_24', 'employment_by_age_group_25_54', 'employment_by_age_group_55'],
          ['employment_by_gender_women', 'employment_by_gender_men']
        ]],

        'employment_change_pct_full_time_jobs' => ['cell' => 'B19', 'type' => 'chg_pct'],
        'employment_change_abs_full_time_jobs' => ['cell' => 'C19', 'type' => 'chg_abs', 'same_sign' => 'employment_change_pct_full_time_jobs'],
        'employment_change_pct_part_time_jobs' => ['cell' => 'B20', 'type' => 'chg_pct'],
        'employment_change_abs_part_time_jobs' => ['cell' => 'C20', 'type' => 'chg_abs', 'same_sign' => 'employment_change_pct_part_time_jobs'],
        'employment_change_pct_total_employment' => ['cell' => 'B18', 'type' => 'chg_pct', 'crosscheck_change_pct' => 'total_employed', 'warn_only' => ['crosscheck_change_pct']],
        'employment_change_abs_total_employment' => ['cell' => 'C18', 'type' => 'chg_abs', 'same_sign' => 'employment_change_pct_total_employment', 'sum' => [
          ['employment_change_abs_full_time_jobs', 'employment_change_abs_part_time_jobs']
        ], 'crosscheck_change_abs' => 'total_employed', 'warn_only' => ['sum', 'crosscheck_change_abs']],

        'employment_rate_change_pct_unemployment' => ['cell' => 'B22', 'type' => 'chg_pct'],
        'employment_rate_pct_unemployment' => ['cell' => 'C22', 'type' => 'pct'],
        'employment_rate_change_pct_participation' => ['cell' => 'B23', 'type' => 'chg_pct'],
        'employment_rate_pct_participation' => ['cell' => 'C23', 'type' => 'pct'],
        'employment_rate_change_pct_unemployment_previous' => ['cell' => 'E22', 'type' => 'chg_pct'],
        'employment_rate_pct_unemployment_previous' => ['cell' => 'F22', 'type' => 'pct'],
        'employment_rate_change_pct_participation_previous' => ['cell' => 'E23', 'type' => 'chg_pct'],
        'employment_rate_pct_participation_previous' => ['cell' => 'F23', 'type' => 'pct'],

        'population_british_columbia' => ['cell' => 'B26', 'type' => 'abs'],
        'population_vancouver_island_coast' => ['cell' => 'B27', 'type' => 'abs'],
        'population_mainland_southwest' => ['cell' => 'B28', 'type' => 'abs'],
        'population_thompson_okanagan' => ['cell' => 'B29', 'type' => 'abs'],
        'population_kootenay' => ['cell' => 'B30', 'type' => 'abs'],
        'population_cariboo' => ['cell' => 'B31', 'type' => 'abs'],
        'population_north_coast_nechako' => ['cell' => 'B32', 'type' => 'abs'],
        'population_northeast' => ['cell' => 'B33', 'type' => 'abs'],

        'unemployment_pct_british_columbia' => ['cell' => 'B41', 'type' => 'pct'],
        'unemployment_pct_british_columbia_previous' => ['cell' => 'E41', 'type' => 'pct'],
        'total_jobs_british_columbia' => ['cell' => 'C41', 'type' => 'abs'],
        'unemployment_pct_vancouver_island_coast' => ['cell' => 'B42', 'type' => 'pct'],
        'unemployment_pct_vancouver_island_coast_previous' => ['cell' => 'E42', 'type' => 'pct'],
        'total_jobs_vancouver_island_coast' => ['cell' => 'C42', 'type' => 'abs'],
        'unemployment_pct_mainland_southwest' => ['cell' => 'B43', 'type' => 'pct'],
        'unemployment_pct_mainland_southwest_previous' => ['cell' => 'E43', 'type' => 'pct'],
        'total_jobs_mainland_southwest' => ['cell' => 'C43', 'type' => 'abs'],
        'unemployment_pct_thompson_okanagan' => ['cell' => 'B44', 'type' => 'pct'],
        'unemployment_pct_thompson_okanagan_previous' => ['cell' => 'E44', 'type' => 'pct'],
        'total_jobs_thompson_okanagan' => ['cell' => 'C44', 'type' => 'abs'],
        'unemployment_pct_kootenay' => ['cell' => 'B45', 'type' => 'pct'],
        'unemployment_pct_kootenay_previous' => ['cell' => 'E45', 'type' => 'pct'],
        'total_jobs_kootenay' => ['cell' => 'C45', 'type' => 'abs'],
        'unemployment_pct_cariboo' => ['cell' => 'B46', 'type' => 'pct'],
        'unemployment_pct_cariboo_previous' => ['cell' => 'E46', 'type' => 'pct'],
        'total_jobs_cariboo' => ['cell' => 'C46', 'type' => 'abs'],
        'unemployment_pct_north_coast_nechako' => ['cell' => 'B47', 'type' => 'pct'],
        'unemployment_pct_north_coast_nechako_previous' => ['cell' => 'E47', 'type' => 'pct'],
        'total_jobs_north_coast_nechako' => ['cell' => 'C47', 'type' => 'abs'],
        'unemployment_pct_northeast' => ['cell' => 'B48', 'type' => 'pct'],
        'unemployment_pct_northeast_previous' => ['cell' => 'E48', 'type' => 'pct'],
        'total_jobs_northeast' => ['cell' => 'C48', 'type' => 'abs'],

        'industry_pct_accommodation_food_services' => ['cell' => 'B59', 'type' => 'chg_pct'],
        'industry_abs_accommodation_food_services' => ['cell' => 'C59', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_accommodation_food_services'],
        'industry_pct_agriculture_fishing' => ['cell' => 'B60', 'type' => 'chg_pct'],
        'industry_abs_agriculture_fishing' => ['cell' => 'C60', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_agriculture_fishing'],
        'industry_pct_construction' => ['cell' => 'B61', 'type' => 'chg_pct'],
        'industry_abs_construction' => ['cell' => 'C61', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_construction'],
        'industry_pct_educational_services' => ['cell' => 'B62', 'type' => 'chg_pct'],
        'industry_abs_educational_services' => ['cell' => 'C62', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_educational_services'],
        'industry_pct_finance_insurance_real_estate' => ['cell' => 'B63', 'type' => 'chg_pct'],
        'industry_abs_finance_insurance_real_estate' => ['cell' => 'C63', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_finance_insurance_real_estate'],
        'industry_pct_health_care_social_assistance' => ['cell' => 'B64', 'type' => 'chg_pct'],
        'industry_abs_health_care_social_assistance' => ['cell' => 'C64', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_health_care_social_assistance'],
        'industry_pct_manufacturing' => ['cell' => 'B65', 'type' => 'chg_pct'],
        'industry_abs_manufacturing' => ['cell' => 'C65', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_manufacturing'],
        'industry_pct_other_primary' => ['cell' => 'B66', 'type' => 'chg_pct'],
        'industry_abs_other_primary' => ['cell' => 'C66', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_other_primary'],
        'industry_pct_other_private_services' => ['cell' => 'B67', 'type' => 'chg_pct'],
        'industry_abs_other_private_services' => ['cell' => 'C67', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_other_private_services'],
        'industry_pct_professional_scientific_technical_services' => ['cell' => 'B68', 'type' => 'chg_pct'],
        'industry_abs_professional_scientific_technical_services' => ['cell' => 'C68', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_professional_scientific_technical_services'],
        'industry_pct_public_administration' => ['cell' => 'B69', 'type' => 'chg_pct'],
        'industry_abs_public_administration' => ['cell' => 'C69', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_public_administration'],
        'industry_pct_transportation_warehousing' => ['cell' => 'B70', 'type' => 'chg_pct'],
        'industry_abs_transportation_warehousing' => ['cell' => 'C70', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_transportation_warehousing'],
        'industry_pct_utilities' => ['cell' => 'B71', 'type' => 'chg_pct'],
        'industry_abs_utilities' => ['cell' => 'C71', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_utilities'],
        'industry_pct_wholesale_retail_trade' => ['cell' => 'B72', 'type' => 'chg_pct'],
        'industry_abs_wholesale_retail_trade' => ['cell' => 'C72', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_wholesale_retail_trade'],
        'industry_pct_business_building_other_support_services' => ['cell' => 'B73', 'type' => 'chg_pct'],
        'industry_abs_business_building_other_support_services' => ['cell' => 'C73', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_business_building_other_support_services'],
        'industry_pct_information_culture_recreation' => ['cell' => 'B74', 'type' => 'chg_pct'],
        'industry_abs_information_culture_recreation' => ['cell' => 'C74', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_information_culture_recreation']
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function getRouteName(): string {
    return 'workbc_ssot.lmmu';
  }
}
