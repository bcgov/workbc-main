<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\workbc_ssot\Form\SsotUploadFormBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Tests the TOC-driven engine of SsotUploadFormBase with in-memory workbooks.
 */
#[Group('workbc_ssot')]
#[CoversClass(SsotUploadFormBase::class)]
class SsotUploadFormBaseTest extends SsotUploadTestBase {

  protected function form(array $toc = [], array $period = ['granularity' => 'year']): TestGenericForm {
    $form = new TestGenericForm($toc, $period);
    $form->setStringTranslation($this->getStringTranslationStub());
    return $form;
  }

  /**
   * A workbook with a small table on "Data" and a record on "Summary".
   *
   * Data!A2:D5 (header row 2, data rows 3-5, D3:D5 merged):
   *   Region    | #     | %   | Avg
   *   Cariboo   | 100   | 5.5 | 52.6
   *   Kootenay  | -     | 6.1 | (merged)
   *   Northeast | =B3*2 | n/a | (merged)
   */
  protected function workbookFixture(): Spreadsheet {
    $spreadsheet = new Spreadsheet();
    $data = $spreadsheet->getActiveSheet();
    $data->setTitle('Data');
    $data->fromArray([
      ['Region', '#', '%', 'Avg'],
      ['Cariboo', 100, 5.5, 52.6],
      ['Kootenay', '-', 6.1, null],
      ['Northeast', '=B3*2', 'n/a', null],
    ], null, 'A2');
    $data->mergeCells('D3:D5');

    $summary = $spreadsheet->createSheet();
    $summary->setTitle('Summary');
    $summary->setCellValue('A3', 45925); // Excel serial for 2025-09-25.
    $summary->setCellValue('B3', 2944700);
    $summary->setCellValue('C8', ' 349200 ');
    return $spreadsheet;
  }

  protected function tableEntry(array $overrides = []): array {
    return $overrides + [
      'sheet' => 'Data',
      'shape' => 'table',
      'range' => 'A3:D5',
      'write' => 'replace',
      'fill_down' => ['avg'],
      'fields' => [
        'region' => ['type' => 'region'],
        'num' => ['type' => 'abs'],
        'pct' => ['type' => 'pct'],
        'avg' => ['type' => 'pct'],
      ],
    ];
  }

  /**
   */
  public function testContextForMonthlyAndYearlyPeriods(): void {
    $form = $this->form();

    $ctx = $form->context(2026, 5);
    $this->assertSame(['year' => 2026, 'month' => 5, 'prev_year' => 2026, 'prev_month' => 4], array_intersect_key($ctx, array_flip(['year', 'month', 'prev_year', 'prev_month'])));
    $this->assertSame('2026/05/01 08:00', $ctx['ssot_period']);
    $this->assertSame(mktime(0, 0, 0, 5, 1, 2026), $ctx['log_period']);
    $this->assertSame('May 2026', $form->label($ctx));

    // January wraps to December of the previous year.
    $ctx = $form->context(2026, 1);
    $this->assertSame([2025, 12], [$ctx['prev_year'], $ctx['prev_month']]);

    // Yearly: month is NULL, period uses month 01.
    $ctx = $form->context(2024, null);
    $this->assertNull($ctx['month']);
    $this->assertSame(2023, $ctx['prev_year']);
    $this->assertSame('2024/01/01 08:00', $ctx['ssot_period']);
    $this->assertSame(mktime(0, 0, 0, 1, 1, 2024), $ctx['log_period']);
    $this->assertSame('2024', $form->label($ctx));
  }

  /**
   */
  public function testInterpolate(): void {
    $form = $this->form();
    $ctx = $form->context(2026, 1);
    $this->assertSame('x?year=eq.2025&month=eq.12', $form->fill('x?year=eq.{prev_year}&month=eq.{prev_month}', $ctx));
    $this->assertSame('2026 Survey {unknown}', $form->fill('{year} Survey {unknown}', $ctx));
    $this->assertNull($form->fill(null, $ctx));
  }

  /**
   */
  public function testFindSheetIsCaseInsensitive(): void {
    $form = $this->form();
    $wb = $this->workbookFixture();
    $this->assertSame('Data', $form->sheet($wb, 'data')->getTitle());
    $this->assertSame('Summary', $form->sheet($wb, 'SUMMARY')->getTitle());
    $this->assertNull($form->sheet($wb, 'Sheet3'));
  }

  /**
   */
  public function testTableFieldRulesDeriveColumnsAndSkipValueFields(): void {
    $entry = $this->tableEntry(['range' => 'C10:F12', 'fields' => [
      'year' => ['value' => '{year}'],
      'region' => ['type' => 'region'],
      'a' => ['type' => 'abs'],
      'b' => ['type' => 'pct'],
      'c' => ['type' => 'pct'],
    ]]);
    $rules = $this->form()->rules($entry);
    $this->assertArrayNotHasKey('cell', $rules['year']);
    $this->assertSame(['C', 'D', 'E', 'F'], [$rules['region']['cell'], $rules['a']['cell'], $rules['b']['cell'], $rules['c']['cell']]);
    $this->assertSame([0, 1, 2, 3], [$rules['region']['_col'], $rules['a']['_col'], $rules['b']['_col'], $rules['c']['_col']]);

    // Record rules are passed through untouched.
    $record = ['sheet' => 'S', 'shape' => 'record', 'fields' => ['t' => ['cell' => 'B3', 'type' => 'abs']]];
    $this->assertSame($record['fields'], $this->form()->rules($record));
  }

  /**
   */
  public function testTableFieldCountMustMatchRangeWidth(): void {
    $entry = $this->tableEntry();
    array_pop($entry['fields']);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Range A3:D5 has 4 columns but 3 cell fields are defined.');
    $this->form()->rules($entry);
  }

  /**
   */
  public function testReadTableNormalizesFillsDownAndEvaluatesFormulas(): void {
    $form = $this->form();
    $data = $form->read($form->sheet($this->workbookFixture(), 'Data'), $this->tableEntry(), $form->context(2024));

    $this->assertSame(3, $data['first_row']);
    $this->assertCount(3, $data['rows']);
    $this->assertSame(['region' => 'Cariboo', 'num' => 100, 'pct' => 5.5, 'avg' => 52.6], $data['rows'][0]);
    // '-' becomes NULL; the merged "avg" cell is filled down.
    $this->assertSame(['region' => 'Kootenay', 'num' => null, 'pct' => 6.1, 'avg' => 52.6], $data['rows'][1]);
    // Formula is evaluated; 'n/a' becomes NULL.
    $this->assertSame(['region' => 'Northeast', 'num' => 200, 'pct' => null, 'avg' => 52.6], $data['rows'][2]);

    // Without fill_down the merged cells stay NULL.
    $data = $form->read($form->sheet($this->workbookFixture(), 'Data'), $this->tableEntry(['fill_down' => []]), $form->context(2024));
    $this->assertNull($data['rows'][1]['avg']);
  }

  /**
   */
  public function testReadRecordWithValuePlaceholders(): void {
    $form = $this->form();
    $entry = [
      'sheet' => 'Summary',
      'shape' => 'record',
      'write' => ['mode' => 'upsert', 'match' => 'year=eq.{year}&month=eq.{month}'],
      'fields' => [
        'year' => ['value' => '{year}'],
        'month' => ['value' => '{month}'],
        'total' => ['cell' => 'B3', 'type' => 'abs'],
        'youth' => ['cell' => 'C8', 'type' => 'abs'],
        'missing' => ['cell' => 'Z99', 'type' => 'abs'],
      ],
    ];
    $data = $form->read($form->sheet($this->workbookFixture(), 'Summary'), $entry, $form->context(2025, 9));
    $this->assertNull($data['first_row']);
    $this->assertSame([['year' => '2025', 'month' => '9', 'total' => 2944700, 'youth' => '349200', 'missing' => null]], $data['rows']);
  }

  /**
   */
  public function testPrepareRowsResolvesLookupsAndCastsNumbers(): void {
    $form = $this->form();
    $rows = [
      ['region' => 'Lower Mainland Southwest', 'num' => '1537688', 'pct' => 43.6, 'avg' => null],
      ['region' => 'Atlantis', 'num' => null, 'pct' => '5', 'avg' => '12.5'],
    ];
    $payload = $form->prepare($this->tableEntry(), $rows);
    $this->assertSame(['region' => 'mainland_southwest', 'num' => 1537688, 'pct' => 43.6, 'avg' => null], $payload[0]);
    // Unknown lookups are left verbatim (validation has already flagged them).
    $this->assertSame(['region' => 'Atlantis', 'num' => null, 'pct' => 5, 'avg' => 12.5], $payload[1]);
  }

  /**
   */
  public function testFullPipelineOnInMemoryWorkbook(): void {
    $form = $this->form([
      'regional' => $this->tableEntry(),
      'summary' => [
        'sheet' => 'Summary',
        'shape' => 'record',
        'write' => ['mode' => 'upsert', 'match' => 'year=eq.{year}'],
        'fields' => ['total' => ['cell' => 'B3', 'type' => 'abs']],
      ],
    ]);
    $out = $form->process($this->workbookFixture(), 2024);

    $this->assertSame(['regional', 'summary'], array_keys($out));
    $regional = static::tally($out['regional']['results']);
    // 3 rows x 4 fields: two NULL cells are warnings, nothing is an error.
    $this->assertSame(['success' => 10, 'warning' => 2, 'error' => 0], $regional);
    // Multi-dataset uploads prefix cells with the tab title.
    $this->assertStringContainsString('Cell Data!B4 has a warning', static::messages($out['regional']['results'], 'warning')[0]);
    $this->assertSame(['cariboo', 'kootenay', 'northeast'], array_column($out['regional']['payload'], 'region'));
    $this->assertSame([['total' => 2944700]], $out['summary']['payload']);
  }

  /**
   * The shipped forms declare consistent TOCs: every table range is as wide as
   * its field list, every type and rule key is known, and inter-cell rules
   * reference fields in the same dataset.
   */
  #[DataProvider('shippedFormProvider')]
  public function testShippedFormDeclarationsAreConsistent(string $class): void {
    /** @var \Drupal\Tests\workbc_ssot\Unit\EngineAccess $form */
    $form = new $class();
    $types = ['abs', 'num', 'pct', 'chg_abs', 'chg_pct', 'industry', 'region'];
    $rule_keys = ['cell', 'value', 'type', 'exceptions', 'same_sign', 'equal', 'sum', 'crosscheck_value', 'crosscheck_change_abs', 'crosscheck_change_pct', 'warn_only'];

    $this->assertNotEmpty($form->toc());
    $this->assertContains($form->period()['granularity'], ['year', 'month']);
    foreach ($form->toc() as $endpoint => $entry) {
      $this->assertContains($entry['shape'], ['record', 'table'], $endpoint);
      $rules = $form->rules($entry); // throws on width mismatch
      foreach ($rules as $field => $rule) {
        $unknown = array_diff(array_keys($rule), $rule_keys, ['_col']);
        $this->assertSame([], $unknown, "$endpoint.$field has unknown rule keys");
        if (isset($rule['type'])) {
          $this->assertContains($rule['type'], $types, "$endpoint.$field");
        }
        foreach (['same_sign', 'equal', 'crosscheck_value', 'crosscheck_change_abs', 'crosscheck_change_pct'] as $ref) {
          if (isset($rule[$ref])) {
            $this->assertArrayHasKey($rule[$ref], $rules, "$endpoint.$field.$ref");
          }
        }
        foreach ($rule['sum'] ?? [] as $group) {
          foreach ($group as $ref) {
            $this->assertArrayHasKey($ref, $rules, "$endpoint.$field.sum");
          }
        }
      }
      foreach ($entry['fill_down'] ?? [] as $field) {
        $this->assertArrayHasKey($field, $rules, "$endpoint.fill_down");
      }
      if ($entry['shape'] === 'record') {
        $this->assertArrayNotHasKey('range', $entry, $endpoint);
      }
    }
  }

  public static function shippedFormProvider(): array {
    return [
      'LMMU' => [TestLmmuForm::class],
      'Industry Profile' => [TestIndustryProfileForm::class],
      'Labour Force Survey' => [TestLabourForceSurveyForm::class],
    ];
  }

}
