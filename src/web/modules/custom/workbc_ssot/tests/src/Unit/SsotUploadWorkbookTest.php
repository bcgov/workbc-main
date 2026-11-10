<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use PHPUnit\Framework\Attributes\Group;

/**
 * Regression tests running the shipped forms against real workbooks and
 * comparing the resulting payloads with what the workbc-ssot migrator produced.
 */
#[Group('workbc_ssot')]
class SsotUploadWorkbookTest extends SsotUploadTestBase {

  /**
   * Compare a table payload with a migrator CSV, column by column.
   *
   * The first column (industry/region) is skipped because the migrator
   * normalises it in SQL after loading. CSV rows may carry trailing empty
   * columns when the tab is wider than the dataset's range.
   */
  protected function assertPayloadMatchesCsv(array $payload, array $csv, string $endpoint): void {
    $this->assertCount(count($csv), $payload, "$endpoint row count");
    foreach ($payload as $i => $row) {
      $expected = $csv[$i];
      $this->assertGreaterThanOrEqual(count($row), count($expected), "$endpoint row $i column count");
      $this->assertSame('', implode('', array_slice($expected, count($row))), "$endpoint row $i has data beyond the declared range");
      $j = 0;
      foreach ($row as $field => $value) {
        $cell = $expected[$j++];
        if ($j === 1) {
          continue;
        }
        $this->assertCellEquals($cell, $value, "$endpoint row $i $field");
      }
    }
  }

  /**
   * Labour Force Survey: 4 datasets over 2 tabs.
   */
  public function testLabourForceSurveyWorkbook(): void {
    $form = new TestLabourForceSurveyForm();
    $form->setStringTranslation($this->getStringTranslationStub());
    $out = $form->process(static::workbook('lfs_2024.xlsx'), 2024);

    $this->assertSame([
      'labour_force_survey_industry',
      'labour_force_survey_regional_employment',
      'labour_force_survey_regional_industry_region',
      'labour_force_survey_regional_industry_province',
    ], array_keys($out));

    foreach ($out as $endpoint => $d) {
      $this->assertSame([], static::messages($d['results'], 'error'), "$endpoint must validate without errors");
      $this->assertPayloadMatchesCsv($d['payload'], static::csv("$endpoint.csv"), $endpoint);
    }

    // Row counts follow the declared ranges.
    $this->assertCount(19, $out['labour_force_survey_industry']['rows']);
    $this->assertCount(8, $out['labour_force_survey_regional_employment']['rows']);
    $this->assertCount(8, $out['labour_force_survey_regional_industry_region']['rows']);
    $this->assertCount(8, $out['labour_force_survey_regional_industry_province']['rows']);

    // Only the deliberately blank "British Columbia" row of the province
    // dataset produces warnings.
    $this->assertSame(0, static::tally($out['labour_force_survey_industry']['results'])['warning']);
    $this->assertSame(0, static::tally($out['labour_force_survey_regional_employment']['results'])['warning']);
    $this->assertSame(0, static::tally($out['labour_force_survey_regional_industry_region']['results'])['warning']);
    $province_warnings = static::messages($out['labour_force_survey_regional_industry_province']['results'], 'warning');
    $this->assertGreaterThan(0, count($province_warnings));
    foreach ($province_warnings as $message) {
      $this->assertMatchesRegularExpression('/Cell Regional Profiles![A-Z]+35 has a warning/', $message);
    }

    // Every key column resolves to an SSoT key.
    $industries = array_column($out['labour_force_survey_industry']['payload'], 'industry');
    $this->assertSame('Total, All Industries', $industries[0]);
    foreach (array_slice($industries, 1) as $key) {
      $this->assertMatchesRegularExpression('/^[a-z_]+$/', $key);
    }
    $this->assertSame(array_keys(static::regions()), array_column($out['labour_force_survey_regional_employment']['payload'], 'region'));

    // fill_down: the merged "provincial average" cells repeat on every row.
    $avg = array_column($out['labour_force_survey_industry']['rows'], 'workforce_provincial_average_pct_men');
    $this->assertCount(1, array_unique($avg));
    $this->assertNotNull($avg[0]);
  }

  /**
   * Industry Profile: single table dataset, year check cell.
   */
  public function testIndustryProfileWorkbook(): void {
    $form = new TestIndustryProfileForm();
    $form->setStringTranslation($this->getStringTranslationStub());
    $wb = static::workbook('industry_profile_2026.xlsx');

    $check = $form->period()['check'];
    $this->assertSame(2026, $form->sheet($wb, $check['sheet'])->getCell($check['cell'])->getCalculatedValue());

    $out = $form->process($wb, 2026);
    $d = $out['industry_outlook'];
    $this->assertSame([], static::messages($d['results'], 'error'));
    $this->assertSame(0, static::tally($d['results'])['warning']);
    $this->assertCount(18, $d['rows']);
    $this->assertPayloadMatchesCsv($d['payload'], static::csv('industry_outlook.csv'), 'industry_outlook');
    foreach (array_column($d['payload'], 'industry') as $key) {
      $this->assertMatchesRegularExpression('/^[a-z_]+$/', $key, 'industry resolved to key');
    }
    // Growth rates are legitimately negative and must be accepted.
    $this->assertLessThan(0, min(array_column($d['payload'], 'annual_employment_growth_rate_pct_first5y')));
  }

  /**
   * LMMU: single record dataset, Excel-date check cell, crosscheck rules.
   */
  public function testLmmuWorkbook(): void {
    $form = new TestLmmuForm();
    $form->setStringTranslation($this->getStringTranslationStub());
    $wb = static::workbook('lmmu_2025_09.xlsx');

    $check = $form->period()['check'];
    $serial = $form->sheet($wb, $check['sheet'])->getCell($check['cell'])->getCalculatedValue();
    $this->assertSame('2025-09', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($serial)->format('Y-m'));

    $out = $form->process($wb, 2025, 9);
    $d = $out['monthly_labour_market_updates'];
    $this->assertSame([], static::messages($d['results'], 'error'));
    $this->assertCount(1, $d['rows']);
    $this->assertNull($d['first_row']);

    // Payload matches the migrator CSV (header row gives the column names).
    $csv = static::csv('monthly_labour_market_updates_2025_09.csv');
    $expected = array_combine($csv[0], $csv[1]);
    $payload = $d['payload'][0];
    $this->assertSame('2025', $payload['year']);
    $this->assertSame('9', $payload['month']);
    $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($payload), 'same columns as migrator');
    foreach ($expected as $field => $value) {
      if (in_array($field, ['year', 'month'])) {
        continue;
      }
      $this->assertCellEquals($value, $payload[$field], $field);
    }

    // Crosscheck: when the previous month's values match the "_previous"
    // columns everything passes; a perturbed previous month is an error.
    $rules = $form->rules($form->toc()['monthly_labour_market_updates']);
    $row = $d['rows'][0];
    $previous = [
      'employment_by_age_group_15_24' => $row['employment_by_age_group_15_24_previous'],
      'employment_by_age_group_25_54' => $row['employment_by_age_group_25_54_previous'],
      'employment_by_age_group_55' => $row['employment_by_age_group_55_previous'],
      'employment_by_gender_women' => $row['employment_by_gender_women_previous'],
      'employment_by_gender_men' => $row['employment_by_gender_men_previous'],
      'total_unemployed' => $row['total_unemployed_previous'],
      'total_employed' => $row['total_employed'] - $row['employment_change_abs_total_employment'],
    ];
    $results = $form->validateRules($rules, $row, $previous);
    $crosschecks = array_filter($results, fn($r) => str_contains(static::text($r), 'previous month') || str_contains(static::text($r), 'monthly difference'));
    $this->assertNotEmpty($crosschecks);
    $this->assertSame([], static::messages($crosschecks, 'error'));

    $previous['employment_by_age_group_15_24'] += 1;
    $errors = static::messages($form->validateRules($rules, $row, $previous), 'error');
    $this->assertCount(1, $errors);
    $this->assertStringContainsString('Cell B8 (employment_by_age_group_15_24_previous', $errors[0]);
  }

  /**
   * Faults injected into the LFS workbook are reported with precise addresses.
   */
  public function testInjectedFaultsAreReported(): void {
    $form = new TestLabourForceSurveyForm();
    $form->setStringTranslation($this->getStringTranslationStub());
    $wb = static::workbook('lfs_2024.xlsx');
    $wb->getSheetByName('Regional Profiles')->setCellValue('A7', 'Caribou');
    $wb->getSheetByName('Regional Profiles')->setCellValue('B8', -3.25);
    $wb->getSheetByName('Industry Profiles')->setCellValue('A6', 'Hospitality');
    $wb->getSheetByName('Industry Profiles')->setCellValue('D6', 12.5);

    $out = $form->process($wb, 2024);
    $errors = [];
    foreach ($out as $d) {
      $errors = array_merge($errors, static::messages($d['results'], 'error'));
    }
    $this->assertCount(4, $errors);
    $this->assertStringContainsString('Cell Industry Profiles!A6 (industry = Hospitality) does not conform to: A known SSoT industry name.', $errors[0]);
    $this->assertStringContainsString('Cell Industry Profiles!D6 (total_employment = 12.5) does not conform to: Absolute value, positive, no decimals.', $errors[1]);
    $this->assertStringContainsString('Cell Regional Profiles!A7 (region = Caribou) does not conform to: A known SSoT region name.', $errors[2]);
    $this->assertStringContainsString('Cell Regional Profiles!B8 (age_distribution_0_14 = -3.25) does not conform to: Percentage value (0-100)', $errors[3]);

    // Unresolved industry stays verbatim in the payload (the form blocks on the error anyway).
    $this->assertSame('Hospitality', $out['labour_force_survey_industry']['payload'][1]['industry']);
  }

  /**
   * A wrong year in the Industry Profile check cell is detectable.
   */
  public function testIndustryProfileYearCheckCell(): void {
    $form = new TestIndustryProfileForm();
    $wb = static::workbook('industry_profile_2026.xlsx');
    $check = $form->period()['check'];
    $this->assertSame('year', $check['format']);
    $this->assertNotEquals(2025, $form->sheet($wb, $check['sheet'])->getCell($check['cell'])->getCalculatedValue());
  }

}
