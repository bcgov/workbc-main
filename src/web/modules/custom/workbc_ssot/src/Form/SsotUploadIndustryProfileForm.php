<?php

namespace Drupal\workbc_ssot\Form;

/**
 * Upload form for the Industry Profile Yearly Update spreadsheet.
 *
 * @package Drupal\workbc_ssot\Form
 */
class SsotUploadIndustryProfileForm extends SsotUploadFormBase {

  protected string $id = 'industry_outlook';
  protected string $title = 'Industry Profile Yearly Update';
  protected string $label = 'B.C. Labour Market Outlook';
  protected string $event_type = 'industry_outlook_update';
  protected ?string $frontend_path = '/research-labour-market/bcs-economy/industry-profile-yearly-update';

  protected array $period = [
    'granularity' => 'year',
    'check' => ['sheet' => 'Sheet1', 'cell' => 'C2', 'format' => 'year'],
  ];

  // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/industry_outlook.load
  protected array $toc = [
    'industry_outlook' => [
      'sheet' => 'Sheet1',
      'shape' => 'table',
      'range' => 'A3:H20',
      'write' => 'replace',
      'fields' => [
        'industry' => ['type' => 'industry'],
        'openings' => ['type' => 'abs'],
        'share_total_employment_pct_first' => ['type' => 'pct'],
        'share_total_employment_pct_second' => ['type' => 'pct'],
        'share_total_employment_pct_third' => ['type' => 'pct'],
        'annual_employment_growth_rate_pct_first5y' => ['type' => 'chg_pct'],
        'annual_employment_growth_rate_pct_second5y' => ['type' => 'chg_pct'],
        'annual_employment_growth_rate_pct_10y' => ['type' => 'chg_pct'],
      ],
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function getRouteName(): string {
    return 'workbc_ssot.industryprofile';
  }
}
