<?php

namespace Drupal\workbc_ssot\Form;

/**
 * Upload form for the yearly High Opportunity Occupations (HOO) data sheet.
 *
 * The workbook has two tabs that feed four SSoT datasets:
 * - "Sheet1" -> high_opportunity_occupations
 *
 * @package Drupal\workbc_ssot\Form
 */
class SsotUploadHighOpportunityOccupationsForm extends SsotUploadFormBase {

  protected string $id = 'high_opportunity_occupations';
  protected string $title = 'High Opportunity Occupations';
  protected string $label = '{year} High Opportunity Occupations';
  protected ?string $author = 'BC Labour Market Information Office';

  protected bool $repo_default = FALSE;

  // The sheet has no reliable period cell: trust the year selected in the form.
  protected array $period = ['granularity' => 'year'];

  protected array $toc = [
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/high_opportunity_occupations.load
    'high_opportunity_occupations' => [
      'sheet' => 'Sheet 1',
      'shape' => 'table',
      'range' => 'A2:O',
      'write' => 'replace',
      'fields' => [
        'occupation' => [],
        'openings_forecast' => ['type' => 'abs'],
        'wage_rate_low' => ['type' => 'num', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'wage_rate_median' => ['type' => 'num', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'wage_rate_high' => ['type' => 'num', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'annual_salary_median' => ['type' => 'num'],
        'occupational_interest' => ['type' => 'interest', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'skills' => ['type' => 'ignore'],
        'skill_1' => ['type' => 'skill', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'skill_2' => ['type' => 'skill', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'skill_3' => ['type' => 'skill', 'validators' => ['blank_if' => ['callback' => 'validateBlankIfNull']]],
        'noc' => [],
        'hash_noc' => ['type' => 'ignore'],
        'teer' => ['type' => 'teer'],
        'region' => ['type' => 'region'],
      ],
    ],
  ];


  /**
   * {@inheritdoc}
   */
  protected function getRouteName(): string {
    return 'workbc_ssot.high_opportunity_occupations';
  }
}
