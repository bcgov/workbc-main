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

  #[DataProvider('blankIfProvider')]
  public function testConditionalBlanks($value, string $region, bool $warn_only, string $expected): void {
    // The lookup field comes last to exercise order-independent resolution.
    $rules = [
      'share' => [
        'cell' => 'B',
        'type' => 'pct',
        'validators' => ['blank_if' => [
          'callback' => 'validateBlankIf',
          'options' => ['field' => 'region', 'value' => 'british_columbia'],
        ]],
        'warn_only' => $warn_only ? ['blank_if'] : [],
      ],
      'region' => ['cell' => 'A', 'type' => 'region'],
    ];
    $dataset = ['share' => $value, 'region' => $region];
    $results = $this->form->validateRules($rules, $dataset, null, 35, 'Regional Profiles');
    $this->assertSame([$expected, 'success'], array_column($results, 'result'));
    if ($region === 'British Columbia') {
      $this->assertStringContainsString('Regional Profiles!B35', static::text($results[0]));
      $this->assertStringContainsString('must be blank when region = british_columbia.', static::text($results[0]));
    }
    $this->assertSame(['share' => $value, 'region' => $region], $dataset);
  }

  public static function blankIfProvider(): array {
    return [
      'expected blank' => [null, 'British Columbia', false, 'success'],
      'zero is nonblank' => [0, 'British Columbia', false, 'error'],
      'percentage is nonblank' => [5.5, 'British Columbia', false, 'error'],
      'invalid text is nonblank' => ['invalid', 'British Columbia', false, 'error'],
      'downgraded violation' => [0, 'British Columbia', true, 'warning'],
      'other region valid percentage' => [5.5, 'Cariboo', false, 'success'],
      'other region blank warning' => [null, 'Cariboo', false, 'warning'],
      'other region type error' => [-5, 'Cariboo', true, 'error'],
    ];
  }

  public function testBlankIfSupportsNonLookupFields(): void {
    $rules = [
      'share' => ['cell' => 'B', 'type' => 'pct', 'validators' => [[
        'callback' => 'validateBlankIf', 'options' => ['field' => 'category', 'value' => 'total'],
      ]]],
      'category' => ['cell' => 'A'],
    ];
    $results = $this->form->validateRules($rules, ['share' => null, 'category' => 'total']);
    $this->assertSame(['success'], array_column($results, 'result'));
  }

  #[DataProvider('invalidBlankIfProvider')]
  public function testInvalidBlankIfIsRejected($condition): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('Invalid blank-if options for field share');
    $this->form->validateRules([
      'share' => ['cell' => 'B', 'type' => 'pct', 'validators' => [[
        'callback' => 'validateBlankIf', 'options' => $condition,
      ]]],
      'region' => ['cell' => 'A', 'type' => 'region'],
    ], ['share' => null, 'region' => 'British Columbia']);
  }

  public static function invalidBlankIfProvider(): array {
    return [
      [['value' => 'british_columbia']],
      [['field' => 'region']],
      [['field' => ['region'], 'value' => 'british_columbia']],
      [['field' => 'missing', 'value' => 'british_columbia']],
      [['field' => 'region', 'value' => 'british_columbia', 'lookup' => 'unknown']],
      [['field' => 'region', 'value' => 'british_columbia', 'exceptions' => []]],
      [['field' => 'region', 'value' => []]],
    ];
  }

  public function testBlankIfResolvesIndustryExceptions(): void {
    $rules = [
      'share' => ['cell' => 'B', 'type' => 'pct', 'validators' => [[
        'callback' => 'validateBlankIf',
        'options' => ['field' => 'industry', 'value' => 'Total, All Industries'],
      ]]],
      'industry' => ['cell' => 'A', 'type' => 'industry', 'exceptions' => ['Total, All Industries']],
    ];
    $results = $this->form->validateRules($rules, ['share' => null, 'industry' => 'total, all industries']);
    $this->assertSame(['success', 'success'], array_column($results, 'result'));
  }

  #[DataProvider('blankIfLookupProvider')]
  public function testBlankIfResolvesLookupOrUsesRawValue(string $type, string $input, string $expected): void {
    $rules = [
      'share' => ['validators' => [[
        'callback' => 'validateBlankIf',
        'options' => ['field' => 'category', 'value' => $expected],
      ]]],
      'category' => ['type' => $type],
    ];
    $results = $this->form->validateRules($rules, ['share' => 1, 'category' => $input], null, null, '', true);
    $this->assertSame(['error'], array_column($results, 'result'));
  }

  public static function blankIfLookupProvider(): array {
    return [
      'industry key' => ['industry', 'Accommodation And Food Services', 'accommodation_food_services'],
      'unknown region' => ['region', 'Caribou', 'Caribou'],
      'unknown industry' => ['industry', 'Hospitality', 'Hospitality'],
    ];
  }

  public function testBlankIfConditionIsEscapedOnce(): void {
    $rules = [
      'share' => ['validators' => [[
        'callback' => 'validateBlankIf',
        'options' => ['field' => 'category', 'value' => '<total>'],
      ]]],
      'category' => [],
    ];
    $results = $this->form->validateRules($rules, ['share' => 3, 'category' => '<total>']);
    $message = (string) $results[0]['message'];
    $this->assertStringContainsString('&lt;total&gt;', $message);
    $this->assertStringNotContainsString('&amp;lt;', $message);
  }

  public function testBlankIfRequiresReferencedFieldDescriptor(): void {
    $rules = ['share' => ['validators' => [[
      'callback' => 'validateBlankIf',
      'options' => ['field' => 'category', 'value' => 'total'],
    ]]]];
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('Invalid blank-if options for field share');
    $this->form->validateRules($rules, ['share' => null, 'category' => 'total']);
  }

  public function testLegacyBlankIfRuleFailsExplicitly(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('blank_if rule for field share has been replaced');
    $this->form->validateRules(['share' => ['blank_if' => ['field' => 'region', 'value' => 'british_columbia']]], ['share' => null]);
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
      // num: positive, any number of decimals.
      ['num', 17.81, 'success'],
      ['num', 17.815, 'success'],
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
      // skill: one known skill name, ignoring case and surrounding whitespace.
      ['skill', 'Critical Thinking', 'success'],
      ['skill', ' critical thinking ', 'success'],
      ['skill', 'Judgment and Decision Making', 'success'],
      ['skill', 'Operation Monitoring', 'error'],
      ['skill', 'Critical Thinking, Active Learning', 'error'],
      // interest: comma-separated known interests, ignoring case and whitespace.
      ['interest', 'Social', 'success'],
      ['interest', 'Realistic, Conventional, Investigative', 'success'],
      ['interest', 'realistic,SOCIAL', 'success'],
      ['interest', 'Realistic, Bogus', 'error'],
      ['interest', 'Realistic; Social', 'error'],
      ['interest', ',', 'error'],
      // teer: a known TEER category, as a number or numeric text.
      ['teer', 0, 'success'],
      ['teer', '5', 'success'],
      ['teer', 2.0, 'success'],
      ['teer', 6, 'error'],
      ['teer', 2.5, 'error'],
      ['teer', 'TEER 2', 'error'],
    ];
  }

  /**
   */
  public function testWarnOnlyDowngradesTypeErrors(): void {
    $this->assertSame(['warning'], $this->kinds(['type' => 'pct', 'warn_only' => ['pct']], -5));
    $this->assertSame(['error'], $this->kinds(['type' => 'pct', 'warn_only' => ['sum']], -5));
  }

  public function testCustomValidatorsReceiveTheFullRowAndReportIndependently(): void {
    $seen = [];
    $rules = [
      'num' => [
        'cell' => 'B',
        'type' => 'abs',
        'validators' => [
          'mutating' => function (string $field, array &$row) use (&$seen): array {
            $seen[] = [$field, $row];
            $row['avg'] = -1;
            return ['success' => true, 'explanation' => 'First check'];
          },
          'validateLessThanAverage',
          'unnamed' => fn(string $field, array $row): array => [
            'success' => false, 'explanation' => '<invalid>', 'suggestion' => '<check>',
          ],
        ],
        'warn_only' => ['validateLessThanAverage'],
      ],
      'avg' => ['cell' => 'C'],
    ];
    $row = ['num' => 12, 'avg' => 10];
    $results = $this->form->validateRules($rules, $row, null, 8, 'Data');
    $this->assertSame([['num', $row]], $seen);
    $this->assertSame(['success', 'warning', 'error', 'success'], array_column($results, 'result'));
    $this->assertStringContainsString('Data!B8 (num = 12)', static::text($results[1]));
    $this->assertStringContainsString('Check the average.', static::text($results[1]));
    $this->assertStringContainsString('&lt;invalid&gt;', (string) $results[2]['message']);
    $this->assertStringContainsString('&lt;check&gt;', (string) $results[2]['message']);
    $this->assertSame(['num' => 12, 'avg' => 10], $row);

    $rules['num']['warn_only'] = ['validators'];
    $results = $this->form->validateRules($rules, $row);
    $this->assertSame(['success', 'warning', 'warning', 'success'], array_column($results, 'result'));
  }

  public function testCustomValidatorsRunForLookupAndConditionalBlankFields(): void {
    $validator = fn(string $field, array $row): array => ['success' => false, 'explanation' => 'Custom'];
    $results = $this->form->validateRules([
      'region' => ['cell' => 'A', 'type' => 'region', 'validators' => ['custom' => $validator], 'warn_only' => ['custom']],
      'share' => ['cell' => 'B', 'type' => 'pct', 'validators' => [
        $validator,
        ['callback' => 'validateBlankIf', 'options' => ['field' => 'region', 'value' => 'british_columbia']],
      ]],
    ], ['region' => 'British Columbia', 'share' => null], null, 3);
    $this->assertSame(['warning', 'success', 'error', 'success'], array_column($results, 'result'));
    $this->assertStringContainsString('Please correct the value.', static::text($results[2]));
  }

  public function testCustomValidatorOptionsArePassedToTheCallback(): void {
    $seen = [];
    $validator = function (string $field, array &$row, array $options) use (&$seen): array {
      $seen = [$field, $row, $options];
      return ['success' => $row[$field] <= $row[$options['field']], 'explanation' => 'Within limit'];
    };
    $results = $this->form->validateRules([
      'value' => ['cell' => 'B2', 'validators' => ['withinLimit' => [
        'callback' => $validator, 'options' => ['field' => 'limit', 'value' => 42],
      ]]],
      'limit' => ['cell' => 'C2'],
    ], ['value' => 4, 'limit' => 10]);
    $this->assertSame(['value', ['value' => 4, 'limit' => 10], ['field' => 'limit', 'value' => 42]], $seen);
    $this->assertSame(['success'], array_column($results, 'result'));

    $rules = ['value' => [
      'validators' => [['callback' => 'validateLimit', 'options' => ['limit' => 3]]],
      'warn_only' => ['validateLimit'],
    ]];
    $this->assertSame(['warning'], array_column($this->form->validateRules($rules, ['value' => 4]), 'result'));
  }

  public function testCustomValidatorsReceiveFieldDescriptorsWithoutChangingOtherCallbacks(): void {
    $seen = [];
    $rules = [
      'value' => [
        'cell' => 'B', 'type' => 'abs',
        'validators' => [
          function (string $field, array &$row, array $options, array &$fields) use (&$seen): array {
            $seen[] = [$field, $row, $options, $fields['region']];
            $fields['region']['type'] = 'changed';
            return ['success' => true];
          },
          ['callback' => function (string $field, array $row, array $options, array $fields) use (&$seen): array {
            $seen[] = [$field, $row, $options, $fields['region']];
            return ['success' => true];
          }, 'options' => ['limit' => 10]],
          function ($field, $row, $options = 'default') use (&$seen): array {
            $seen[] = [$options, func_num_args()];
            return ['success' => true];
          },
          function ($field, $row, ...$extra) use (&$seen): array {
            $seen[] = $extra;
            return ['success' => true];
          },
        ],
      ],
      'region' => ['cell' => 'A', 'type' => 'region', 'exceptions' => ['example']],
    ];
    $row = ['value' => 4, 'region' => 'Cariboo'];
    $results = $this->form->validateRules($rules, $row);
    $this->assertSame(['success', 'success'], array_column($results, 'result'));
    $this->assertSame(['value', $row, [], $rules['region']], $seen[0]);
    $this->assertSame(['value', $row, ['limit' => 10], $rules['region']], $seen[1]);
    $this->assertSame(['default', 2], $seen[2]);
    $this->assertSame([[], $rules], $seen[3]);
    $this->assertSame('region', $rules['region']['type']);
  }

  #[DataProvider('customValidatorActionProvider')]
  public function testCustomValidatorActionsAndMissingExplanations(array $response, array $warn_only, array $expected, bool $next_called): void {
    $called = false;
    $results = $this->form->validateRules([
      'f' => [
        'cell' => 'A', 'type' => 'pct', 'warn_only' => $warn_only,
        'validators' => [
          'first' => fn() => $response,
          'next' => function () use (&$called): array {
            $called = true;
            return ['success' => true, 'explanation' => 'Next validator'];
          },
        ],
      ],
      'other' => ['cell' => 'B', 'type' => 'abs'],
    ], ['f' => -1, 'other' => 2], null, 3);
    $this->assertSame($expected, array_column($results, 'result'));
    $this->assertSame($next_called, $called);
    if (!$response['success'] && empty(trim($response['explanation'] ?? ''))) {
      $this->assertStringContainsString('No explanation given.', static::text($results[0]));
    }
  }

  public static function customValidatorActionProvider(): array {
    return [
      'silent continue' => [['success' => true, 'action' => 'continue'], [], ['success', 'error', 'success'], true],
      'silent skip' => [['success' => true, 'action' => 'skip'], [], ['success'], false],
      'pass skip' => [['success' => true, 'action' => 'skip', 'explanation' => 'Done'], [], ['success', 'success'], false],
      'fail skip' => [['success' => false, 'action' => 'skip'], [], ['error', 'success'], false],
      'warning skip' => [['success' => false, 'action' => 'skip', 'explanation' => '  '], ['first'], ['warning', 'success'], false],
      'fail continue' => [['success' => false], [], ['error', 'success', 'error', 'success'], true],
    ];
  }

  #[DataProvider('invalidCustomValidatorProvider')]
  public function testInvalidCustomValidatorsFailExplicitly($validators, string $message): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage($message);
    $this->form->validateRules(['f' => ['cell' => 'B2', 'validators' => $validators]], ['f' => 1]);
  }

  public static function invalidCustomValidatorProvider(): array {
    return [
      'not an array' => ['invalid', 'Invalid validators for field f'],
      'missing method' => [['notAValidator'], 'Invalid validator notAValidator for field f'],
      'empty name' => [['' => fn() => []], 'Invalid validator name for field f'],
      'descriptor without callback' => [['check' => ['options' => []]], 'Invalid validator definition for field f'],
      'non-array options' => [['check' => ['callback' => 'validateLessThanAverage', 'options' => 'bad']], 'Invalid validator definition for field f'],
      'unknown descriptor key' => [['check' => ['callback' => 'validateLessThanAverage', 'option' => []]], 'Invalid validator definition for field f'],
    ];
  }

  #[DataProvider('invalidCustomResultProvider')]
  public function testInvalidCustomResultsFailExplicitly($response): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('Invalid result from validator check for field f');
    $this->form->validateRules(['f' => ['validators' => ['check' => fn() => $response]]], ['f' => 1]);
  }

  public static function invalidCustomResultProvider(): array {
    return [
      'non-array' => [true],
      'missing success' => [['explanation' => 'Reason']],
      'non-boolean success' => [['success' => 1, 'explanation' => 'Reason']],
      'non-string explanation' => [['success' => true, 'explanation' => null]],
      'non-string suggestion' => [['success' => false, 'explanation' => 'Reason', 'suggestion' => null]],
      'invalid action' => [['success' => true, 'action' => 'stop']],
      'null action' => [['success' => true, 'action' => null]],
    ];
  }

  public function testCustomValidatorExceptionsIdentifyFieldAndPreserveCause(): void {
    try {
      $this->form->validateRules(['f' => ['cell' => 'B', 'validators' => ['bad' => function () {
        throw new \RuntimeException('broken');
      }]]], ['f' => 1], null, 3, 'Data');
      $this->fail('Expected the validator exception.');
    }
    catch (\UnexpectedValueException $error) {
      $this->assertStringContainsString('Validator bad failed for field f at Data!B3: broken', $error->getMessage());
      $this->assertInstanceOf(\RuntimeException::class, $error->getPrevious());
    }
  }

  /**
   */
  public function testMessagesCarryCellAddressRowAndSheet(): void {
    $rules = ['total' => ['cell' => 'F', 'type' => 'abs']];
    $results = $this->form->validateRules($rules, ['total' => 12.5], null, 9, 'Industry Profiles');
    $this->assertCount(1, $results);
    $this->assertSame('error', $results[0]['result']);
    $text = static::text($results[0]);
    $this->assertStringContainsString('Industry Profiles!F9', $text);
    $this->assertStringContainsString('total = 12.5', $text);
    $this->assertStringContainsString('Absolute value, positive, no decimals.', $text);

    // Record-shaped: no row, no sheet prefix.
    $results = $this->form->validateRules(['t' => ['cell' => 'B18', 'type' => 'abs']], ['t' => 5]);
    $this->assertStringContainsString('B18 ', static::text($results[0]));
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
    $this->assertStringContainsString('B19 (pct = -5.4) and C19 (abs = 10708)', static::messages($bad, 'error')[0]);

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
    $this->assertStringContainsString('Regional Profiles!A7 (region = Caribou)', static::text($bad[0]));

    // A blank key column is always an error, even with warn_only.
    $blank = $this->form->validateRules(['region' => ['cell' => 'A', 'type' => 'region', 'warn_only' => ['region']]], ['region' => null], null, 7);
    $this->assertSame('error', $blank[0]['result']);
    $this->assertStringContainsString('is blank', static::text($blank[0]));

    $exc = $this->form->validateRules(['industry' => ['cell' => 'A', 'type' => 'industry', 'exceptions' => ['Total, All Industries']]], ['industry' => 'Total, All Industries'], null, 5);
    $this->assertSame('success', $exc[0]['result']);
  }

  /**
   */
  public function testStringRules(): void {
    $rules = ['skill_1' => ['cell' => 'I', 'type' => 'skill']];
    $bad = $this->form->validateRules($rules, ['skill_1' => 'Operation Monitoring'], null, 7);
    $this->assertSame('error', $bad[0]['result']);
    $this->assertStringContainsString('I7 (skill_1 = Operation Monitoring)', static::text($bad[0]));
    $this->assertStringContainsString('A known SSoT skill name.', static::text($bad[0]));

    // An unknown value can be downgraded with warn_only.
    $this->assertSame(['warning'], $this->kinds(['type' => 'interest', 'warn_only' => ['interest']], 'Realistic, Bogus'));

    // Only string types are dispatched.
    $this->assertTrue($this->form->validateString('skill', 'Active Learning', []));
    $this->assertFalse($this->form->validateString('interest', 'Active Learning', []));
    $this->assertNull($this->form->validateString('abs', 'Active Learning', []));
  }

  /**
   */
  public function testNocRules(): void {
    // A code is text: it skips the numeric checks and reports nothing.
    foreach (['#00018', '00018', 10010] as $noc) {
      $this->assertSame([], $this->kinds(['type' => 'noc'], $noc), (string) $noc);
    }

    // A blank code, or a lone # prefix, is an error.
    foreach ([null, '', '#'] as $blank) {
      $this->assertSame(['error'], $this->kinds(['type' => 'noc'], $blank), var_export($blank, true));
    }
    $results = $this->form->validateRules(['noc' => ['cell' => 'A', 'type' => 'noc']], ['noc' => null], null, 5, 'Provincial Outlook');
    $this->assertStringContainsString('Provincial Outlook!A5 (noc) is blank', static::text($results[0]));

    // The blank error can be downgraded with warn_only.
    $this->assertSame(['warning'], $this->kinds(['type' => 'noc', 'warn_only' => ['noc']], null));
  }

}
