<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\workbc_ssot\Form\SsotUploadValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the declarative cell validation rules in SsotUploadValidation.
 */
#[Group('workbc_ssot')]
#[CoversClass(SsotUploadValidation::class)]
class SsotUploadValidationTest extends SsotUploadTestBase {

  protected TestGenericForm $form;

  protected function setUp(): void {
    parent::setUp();
    $this->form = new TestGenericForm();
    $this->form->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Run a single-field rule against one value and return the result kinds.
   */
  protected function kinds(array $rule, $value, ?array $crosscheck = null): array {
    $rules = ['f' => ['cell' => 'B2'] + $rule];
    return array_column($this->form->validateRules($rules, ['f' => $value], $crosscheck), 'result');
  }

  /**
   */
  #[DataProvider('typeProvider')]
  public function testTypeRules(string $type, $value, string $expected): void {
    $this->assertSame([$expected], $this->kinds(['type' => $type], $value), "$type($value)");
  }

  public static function typeProvider(): array {
    return [
      // abs: positive integer.
      ['abs', 42, 'success'],
      ['abs', 0, 'success'],
      ['abs', '12', 'success'],
      ['abs', -1, 'error'],
      ['abs', 1.5, 'error'],
      ['abs', null, 'warning'],
      ['abs', 'n/a', 'warning'],
      // num: positive, up to 2 decimals.
      ['num', 17.81, 'success'],
      ['num', 17.815, 'error'],
      ['num', -0.5, 'error'],
      // pct: 0..100, one decimal.
      ['pct', 52.6, 'success'],
      ['pct', 100, 'success'],
      ['pct', 100.1, 'error'],
      ['pct', -3.25, 'error'],
      ['pct', 12.34, 'error'],
      // chg_pct: |v| <= 50, one decimal.
      ['chg_pct', -1.4, 'success'],
      ['chg_pct', 50, 'success'],
      ['chg_pct', 50.1, 'error'],
      ['chg_pct', 1.25, 'error'],
      // chg_abs: signed integer.
      ['chg_abs', -10708, 'success'],
      ['chg_abs', 3.5, 'error'],
      ['chg_abs', '', 'warning'],
    ];
  }

  /**
   */
  public function testWarnOnlyDowngradesTypeErrors(): void {
    $this->assertSame(['warning'], $this->kinds(['type' => 'pct', 'warn_only' => ['pct']], -5));
    $this->assertSame(['error'], $this->kinds(['type' => 'pct', 'warn_only' => ['sum']], -5));
  }

  /**
   */
  public function testMessagesCarryCellAddressRowAndSheet(): void {
    $rules = ['total' => ['cell' => 'F', 'type' => 'abs']];
    $results = $this->form->validateRules($rules, ['total' => 12.5], null, 9, 'Industry Profiles');
    $this->assertCount(1, $results);
    $this->assertSame('error', $results[0]['result']);
    $text = static::text($results[0]);
    $this->assertStringContainsString('Cell Industry Profiles!F9', $text);
    $this->assertStringContainsString('total = 12.5', $text);
    $this->assertStringContainsString('Absolute value, positive, no decimals.', $text);

    // Record-shaped: no row, no sheet prefix.
    $results = $this->form->validateRules(['t' => ['cell' => 'B18', 'type' => 'abs']], ['t' => 5]);
    $this->assertStringContainsString('Cell B18 ', static::text($results[0]));
  }

  /**
   */
  public function testSameSign(): void {
    $rules = [
      'pct' => ['cell' => 'B19', 'type' => 'chg_pct'],
      'abs' => ['cell' => 'C19', 'type' => 'chg_abs', 'same_sign' => 'pct'],
    ];
    $ok = static::tally($this->form->validateRules($rules, ['pct' => -5.4, 'abs' => -10708]));
    $this->assertSame(['success' => 3, 'warning' => 0, 'error' => 0], $ok);

    $bad = $this->form->validateRules($rules, ['pct' => -5.4, 'abs' => 10708]);
    $this->assertSame(1, static::tally($bad)['error']);
    $this->assertStringContainsString('Cells B19 (pct = -5.4) and C19 (abs = 10708)', static::messages($bad, 'error')[0]);

    // Both blank is acceptable; one blank is not.
    $this->assertSame(0, static::tally($this->form->validateRules($rules, ['pct' => null, 'abs' => null]))['error']);
  }

  /**
   */
  public function testEqual(): void {
    $rules = ['a' => ['cell' => 'A1', 'type' => 'abs'], 'b' => ['cell' => 'B1', 'type' => 'abs', 'equal' => 'a']];
    $this->assertSame(0, static::tally($this->form->validateRules($rules, ['a' => 7, 'b' => 7]))['error']);
    $this->assertSame(1, static::tally($this->form->validateRules($rules, ['a' => 7, 'b' => 8]))['error']);
  }

  /**
   */
  public function testSumWithinTolerance(): void {
    $rules = [
      'a' => ['cell' => 'C8', 'type' => 'abs'],
      'b' => ['cell' => 'C9', 'type' => 'abs'],
      'c' => ['cell' => 'C10', 'type' => 'abs'],
      'total' => ['cell' => 'B3', 'type' => 'abs', 'sum' => [['a', 'b', 'c']]],
    ];
    $row = ['a' => 100, 'b' => 200, 'c' => 300];
    $this->assertSame(0, static::tally($this->form->validateRules($rules, $row + ['total' => 600]))['error']);
    $this->assertSame(0, static::tally($this->form->validateRules($rules, $row + ['total' => 699]))['error'], 'within ±100');
    $bad = $this->form->validateRules($rules, $row + ['total' => 800]);
    $this->assertSame(1, static::tally($bad)['error']);
    $this->assertStringContainsString('cells C8 + C9 + C10 (sum = 600)', static::messages($bad, 'error')[0]);

    // warn_only on the sum rule only.
    $rules['total']['warn_only'] = ['sum'];
    $this->assertSame(['success' => 4, 'warning' => 1, 'error' => 0], static::tally($this->form->validateRules($rules, $row + ['total' => 800])));
  }

  /**
   */
  public function testCrosscheckRules(): void {
    $rules = [
      'now' => ['cell' => 'C8', 'type' => 'abs'],
      'prev' => ['cell' => 'B8', 'type' => 'abs', 'crosscheck_value' => 'now'],
      'chg_abs' => ['cell' => 'C18', 'type' => 'chg_abs', 'crosscheck_change_abs' => 'now'],
      'chg_pct' => ['cell' => 'B18', 'type' => 'chg_pct', 'crosscheck_change_pct' => 'now'],
    ];
    $previous = ['now' => 1000];
    $good = ['now' => 1250, 'prev' => 1000, 'chg_abs' => 250, 'chg_pct' => 20.0];
    $this->assertSame(0, static::tally($this->form->validateRules($rules, $good, $previous))['error']);

    $bad = $this->form->validateRules($rules, ['now' => 1250, 'prev' => 999, 'chg_abs' => 251, 'chg_pct' => 19.9], $previous);
    $errors = static::messages($bad, 'error');
    $this->assertCount(3, $errors);
    $this->assertStringContainsString('previous month cell C8 (now = 1000)', $errors[0]);
    $this->assertStringContainsString('absolute difference = 250', $errors[1]);
    $this->assertStringContainsString('difference percentage = 20', $errors[2]);

    // No crosscheck data: the crosscheck rules are skipped entirely.
    $this->assertSame(4, count($this->form->validateRules($rules, $good, null)));
  }

  /**
   */
  public function testLookupNormalization(): void {
    $this->assertSame('accommodation_food_services', $this->form->industryKey('Accommodation And Food Services'));
    $this->assertSame('finance_insurance_real_estate', $this->form->industryKey('finance, insurance & real estate'));
    $this->assertSame('Total, All Industries', $this->form->industryKey('Total, All Industries', ['Total, All Industries']));
    $this->assertNull($this->form->industryKey('Total, All Industries'));
    $this->assertNull($this->form->industryKey('Hospitality'));
    $this->assertNull($this->form->industryKey(null));

    foreach ([
      'British Columbia' => 'british_columbia',
      'Lower Mainland Southwest' => 'mainland_southwest',
      'Mainland/Southwest' => 'mainland_southwest',
      'Vancouver Island And Coast' => 'vancouver_island_coast',
      'North Coast And Nechako' => 'north_coast_nechako',
      'Thompson Okanagan' => 'thompson_okanagan',
    ] as $name => $key) {
      $this->assertSame($key, $this->form->regionKey($name), $name);
    }
    $this->assertNull($this->form->regionKey('Caribou'));
  }

  /**
   */
  public function testLookupRules(): void {
    $rules = ['region' => ['cell' => 'A', 'type' => 'region']];
    $ok = $this->form->validateRules($rules, ['region' => 'Cariboo'], null, 7, 'Regional Profiles');
    $this->assertSame('success', $ok[0]['result']);
    $this->assertStringContainsString('(cariboo)', static::text($ok[0]));

    $bad = $this->form->validateRules($rules, ['region' => 'Caribou'], null, 7, 'Regional Profiles');
    $this->assertSame('error', $bad[0]['result']);
    $this->assertStringContainsString('Cell Regional Profiles!A7 (region = Caribou)', static::text($bad[0]));

    // A blank key column is always an error, even with warn_only.
    $blank = $this->form->validateRules(['region' => ['cell' => 'A', 'type' => 'region', 'warn_only' => ['region']]], ['region' => null], null, 7);
    $this->assertSame('error', $blank[0]['result']);
    $this->assertStringContainsString('is blank', static::text($blank[0]));

    $exc = $this->form->validateRules(['industry' => ['cell' => 'A', 'type' => 'industry', 'exceptions' => ['Total, All Industries']]], ['industry' => 'Total, All Industries'], null, 5);
    $this->assertSame('success', $exc[0]['result']);
  }

}
