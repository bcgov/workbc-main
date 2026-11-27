<?php

namespace Drupal\workbc_ssot\Form;

/**
 * Upload form for the yearly Labour Force Survey (LFS) data sheet.
 *
 * The workbook has two tabs that feed four SSoT datasets:
 * - "Provincial Outlook" -> career_provincial
 * - "Regional Outlook" -> career_regional
 *
 * @package Drupal\workbc_ssot\Form
 */
class SsotUploadCareerProfileForm extends SsotUploadFormBase {

  private const BC_BLANK_PERCENTAGE = [
    'type' => 'pct',
    'validators' => [
      'blank_if' => [
        'callback' => 'validateBlankIf',
        'options' => ['field' => 'region', 'value' => 'british_columbia'],
      ],
    ],
  ];

  protected string $id = 'career_profile';
  protected string $title = 'Career Profile';
  protected string $label = '{year} Career Profile';
  protected ?string $author = '';

  protected bool $repo_default = FALSE;

  // The sheet has no reliable period cell: trust the year selected in the form.
  protected array $period = ['granularity' => 'year'];

  protected array $toc = [
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/career_provncial.load
    'career_provincial' => [
      'sheet' => 'Provincial Outlook',
      'shape' => 'table',
      'range' => 'A4:L515',
      'write' => 'replace',
      'fields' => [
        'noc' => ['type' => 'noc'],
        'description' => [],
        'forecasted_average_employment_growth_rate_first5y' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'forecasted_average_employment_growth_rate_second5y' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'forecasted_average_employment_growth_rate_10y' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'job_openings_first5y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'job_openings_second5y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'expected_job_openings_10y' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'replacement_of_retiring_workers_10y_pct' => ['type' => 'pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'replacement_of_retiring_workers_10y' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'new_jobs_due_to_economic_growth_10y_pct' => ['type' => 'pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'new_jobs_due_to_economic_growth_10y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
      ],
    ],
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/career_regional.load
    'career_regional' => [
      'sheet' => 'Regional Outlook',
      'shape' => 'table',
      'range' => 'A5:AC516',
      'write' => 'replace',
      'fields' => [
        'noc' => ['type' => 'noc'],
        'description' => [],
        'cariboo_employment_current' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'cariboo_average_annual_employment_growth_10y_pct' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'cariboo_expected_number_of_job_openings_10y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'blank_1' => ['type' => 'ignore'],
        'kootenay_employment_current' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'kootenay_average_annual_employment_growth_10y_pct' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'kootenay_expected_number_of_job_openings_10y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'blank_2' => ['type' => 'ignore'],
        'mainland_southwest_employment_current' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'mainland_southwest_annual_employment_growth_10y_pct' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'mainland_southwest_expected_number_of_job_openings_10y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'blank_3' => ['type' => 'ignore'],
        'north_coast_nechako_employment_current' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'north_coast_nechako_annual_employment_growth_10y_pct' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'north_coast_nechako_expected_number_of_job_openings_10y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'blank_4' => ['type' => 'ignore'],
        'northeast_employment_current' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'northeast_annual_employment_growth_10y_pct' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'northeast_expected_number_of_job_openings_10y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'blank_5' => ['type' => 'ignore'],
        'thompson_okanagan_employment_current' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'thompson_okanagan_annual_employment_growth_10y_pct' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'thompson_okanagan_expected_number_of_job_openings_10y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'blank_6' => ['type' => 'ignore'],
        'vancouver_island_coast_employment_current' => ['type' => 'abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'vancouver_island_coast_annual_employment_growth_10y_pct' => ['type' => 'chg_pct', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'vancouver_island_coast_expected_number_of_job_openings_10y' => ['type' => 'chg_abs', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
      ],
    ],
  ];


  /**
   * {@inheritdoc}
   */
  protected function getRouteName(): string {
    return 'workbc_ssot.career_profile';
  }
}
