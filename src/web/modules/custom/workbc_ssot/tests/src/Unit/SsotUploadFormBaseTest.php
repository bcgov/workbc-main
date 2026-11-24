<?php

namespace Drupal\Tests\workbc_ssot\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Insert;
use Drupal\Core\Database\Query\Update;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\workbc_ssot\Form\SsotUploadFormBase;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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

  public function testOpenRangeUsesLastNonEmptyRowWithinColumns(): void {
    $form = $this->form();
    $workbook = $this->workbookFixture();
    $sheet = $form->sheet($workbook, 'Data');
    $sheet->setCellValue('C6', 'x');
    $sheet->setCellValue('A8', 0);
    $sheet->setCellValue('B9', '  ');
    $sheet->setCellValue('C10', '=""');
    $sheet->getStyle('D11')->getFont()->setBold(true);
    $sheet->setCellValue('E30', 'outside');
    $entry = $this->tableEntry(['range' => 'A3:D', 'fill_down' => [], 'fields' => [
      'region' => ['type' => 'region'],
      'num' => ['type' => 'abs'],
      'unused' => ['type' => 'ignore'],
      'avg' => ['type' => 'pct'],
    ]]);
    $data = $form->read($sheet, $entry, $form->context(2024));
    $this->assertSame('A3:D8', $data['range']);
    $this->assertCount(6, $data['rows']);
    $this->assertSame(0, $data['rows'][5]['region']);
    $this->assertSame(['region' => null, 'num' => null, 'avg' => null], $data['rows'][3]);
    $this->assertSame('A3:D8', $form->metadata('example', $entry, $data)['range']);

    $sheet->setCellValue('C12', '-');
    $data = $form->read($sheet, $entry, $form->context(2024));
    $this->assertSame('A3:D12', $data['range']);
    $this->assertArrayNotHasKey('unused', $data['rows'][9]);
  }

  public function testOpenRangeRequiresDataAtOrAfterStartingRow(): void {
    $form = $this->form();
    $sheet = $form->sheet($this->workbookFixture(), 'Data');
    $sheet->setCellValue('A8', " \t");
    $sheet->setCellValue('B9', '=""');
    $sheet->setCellValue('E20', 'outside');
    $entry = $this->tableEntry(['range' => 'A8:D']);
    $this->expectException(\UnderflowException::class);
    $this->expectExceptionMessage('No data found in range A8:D.');
    $form->read($sheet, $entry, $form->context(2024));
  }

  #[DataProvider('openRangeContentProvider')]
  public function testOpenRangeTreatsValuesInIgnoredColumnsAsContent($value): void {
    $form = $this->form();
    $sheet = (new Spreadsheet())->getActiveSheet();
    $sheet->setCellValue('AB7', $value);
    $entry = $this->tableEntry(['range' => 'AA3:AB', 'fill_down' => [], 'fields' => [
      'value' => ['type' => 'abs'],
      'unused' => ['type' => 'ignore'],
    ]]);
    $data = $form->read($sheet, $entry, $form->context(2024));
    $this->assertSame('AA3:AB7', $data['range']);
    $this->assertCount(5, $data['rows']);
  }

  public static function openRangeContentProvider(): array {
    return [
      'zero' => [0],
      'dash' => ['-'],
      'x' => ['x'],
      'nonempty formula' => ['=1+1'],
    ];
  }

  public function testOpenRangeKeepsWidthChecksAndRequiresResolvedMetadata(): void {
    $form = $this->form();
    $entry = $this->tableEntry(['range' => 'A3:D']);
    $rules = $form->rules($entry);
    $this->assertSame(['A', 'B', 'C', 'D'], array_column($rules, 'cell'));
    $this->assertSame([
      'endpoint' => 'example', 'sheet' => 'Data', 'range' => 'A3:D5', 'match' => 'endpoint',
    ], $form->metadata('example', $entry, ['range' => 'A3:D5']));
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Missing resolved range for example.');
    $form->metadata('example', $entry, []);
  }

  public function testOpenRangeRejectsMismatchedColumnCount(): void {
    $entry = $this->tableEntry(['range' => 'A3:D']);
    array_pop($entry['fields']);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Range A3:D has 4 columns but 3 cell fields are defined.');
    $this->form()->rules($entry);
  }

  public function testResolvedRangeIsUsedForSourceAndQueuedUpload(): void {
    $entry = $this->tableEntry(['range' => 'A3:D']);
    $form = $this->getMockBuilder(TestGenericForm::class)
      ->setConstructorArgs([['example' => $entry]])
      ->onlyMethods(['ssot'])
      ->getMock();
    $form->setStringTranslation($this->getStringTranslationStub());
    $data = $form->read($form->sheet($this->workbookFixture(), 'Data'), $entry, $form->context(2024));
    $calls = [];
    $form->method('ssot')->willReturnCallback(function ($url, $timeout = null, $method = 'GET', $body = null) use (&$calls) {
      $calls[] = [$url, $method, $body];
      return new Response(200, [], '[]');
    });

    $file = $this->createMock(FileInterface::class);
    $file->method('getFilename')->willReturn('example.xlsx');
    $file->method('getFileUri')->willReturn('public://example.xlsx');
    $file_storage = $this->createMock(EntityStorageInterface::class);
    $file_storage->method('load')->with(1)->willReturn($file);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('file')->willReturn($file_storage);
    $repository = $this->createMock(EntityTypeRepositoryInterface::class);
    $repository->method('getEntityTypeFromClass')->with(File::class)->willReturn('file');
    $insert = $this->createMock(Insert::class);
    $insert->method('fields')->willReturnSelf();
    $insert->method('execute')->willReturn(1);
    $update = $this->createMock(Update::class);
    $update->method('fields')->willReturnSelf();
    $update->method('condition')->willReturnSelf();
    $database = $this->createMock(Connection::class);
    $database->method('insert')->willReturn($insert);
    $database->method('update')->willReturn($update);
    $queued = null;
    $queue = $this->createMock(QueueInterface::class);
    $queue->expects($this->once())->method('createItem')->willReturnCallback(function ($item) use (&$queued) {
      $queued = $item;
      return 1;
    });
    $queue_factory = $this->createMock(QueueFactory::class);
    $queue_factory->method('get')->willReturn($queue);
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(2);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $urls = $this->createMock(FileUrlGeneratorInterface::class);
    $urls->method('generateAbsoluteString')->willReturn('https://example.test/example.xlsx');
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $messenger = $this->createMock(MessengerInterface::class);
    $messenger->expects($this->never())->method('addError');
    $container = \Drupal::getContainer();
    foreach ([
      'entity_type.manager' => $manager,
      'entity_type.repository' => $repository,
      'database' => $database,
      'queue' => $queue_factory,
      'current_user' => $account,
      'datetime.time' => $time,
      'file_url_generator' => $urls,
      'logger.factory' => $logger_factory,
      'messenger' => $messenger,
      'cache_tags.invalidator' => $this->createMock(CacheTagsInvalidatorInterface::class),
    ] as $id => $service) {
      $container->set($id, $service);
    }

    $description = $form->confirmationDescription(['example' => $data], $form->context(2024));
    $this->assertStringContainsString('example: A3:D5', (string) $description);
    $state = new FormState();
    $state->set('confirmation', true);
    $state->set('datasets', ['example' => $data]);
    $state->set('context', $form->context(2024));
    $state->set('file_id', 1);
    $state->set('timestamp', 1700000000);
    $state->set('repo', true);
    $form_array = [];
    $form->submitForm($form_array, $state);

    $source_calls = array_values(array_filter($calls, fn($call) => $call[0] === 'sources' && $call[1] === 'POST'));
    $this->assertCount(1, $source_calls);
    $source = json_decode($source_calls[0][2], true, 512, JSON_THROW_ON_ERROR);
    $this->assertSame('A3:D5', $source['range']);
    $this->assertSame('A3:D5', $queued['datasets'][0]['range']);
  }

  public function testIgnoredColumnsAreExcludedFromRowsValidationAndPayload(): void {
    $entry = $this->tableEntry(['fill_down' => [], 'fields' => [
      'unused_region' => ['type' => 'ignore'],
      'year' => ['value' => '{year}'],
      'num' => ['type' => 'abs'],
      'unused_pct' => ['type' => 'ignore'],
      'avg' => ['type' => 'pct'],
      'synthetic' => ['type' => 'synthetic', 'callback' => 'syntheticTotal'],
    ]]);
    $form = $this->form(['data' => $entry]);
    $rules = $form->rules($entry);
    $this->assertSame(['A', 'B', 'C', 'D'], array_column($rules, 'cell'));
    $result = $form->process($this->workbookFixture(), 2024)['data'];
    $this->assertSame(['year' => '2024', 'num' => 100, 'avg' => 52.6, 'synthetic' => 152.6], $result['rows'][0]);
    $this->assertSame($result['rows'][0], $result['payload'][0]);
    $this->assertSame(['success' => 3, 'warning' => 3, 'error' => 0], static::tally($result['results']));
  }

  #[DataProvider('invalidIgnoredDefinitionProvider')]
  public function testInvalidIgnoredDefinitionsFail(array $overrides): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Invalid ignored field definition for unused');
    $this->form()->rules($this->tableEntry($overrides));
  }

  public static function invalidIgnoredDefinitionProvider(): array {
    return [
      'record' => [['shape' => 'record', 'fields' => ['unused' => ['type' => 'ignore']]]],
      'value' => [['fields' => ['unused' => ['type' => 'ignore', 'value' => '1']]]],
      'validation' => [['fields' => ['unused' => ['type' => 'ignore', 'equal' => 'num']]]],
      'fill down' => [['fill_down' => ['unused'], 'fields' => ['unused' => ['type' => 'ignore']]]],
    ];
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

  public function testSyntheticFieldsUseCompletedFilledRowsWithoutConsumingColumns(): void {
    $form = $this->form();
    $entry = $this->tableEntry(['fields' => [
      'synthetic' => ['type' => 'synthetic', 'callback' => 'syntheticTotal'],
      'region' => ['type' => 'region'],
      'num' => ['type' => 'abs'],
      'pct' => ['type' => 'pct'],
      'avg' => ['type' => 'pct'],
    ]]);
    $rules = $form->rules($entry);
    $this->assertArrayNotHasKey('cell', $rules['synthetic']);
    $this->assertArrayNotHasKey('_col', $rules['synthetic']);
    $this->assertSame(['A', 'B', 'C', 'D'], array_column(array_slice($rules, 1), 'cell'));
    $workbook = $this->workbookFixture();
    $data = $form->read($form->sheet($workbook, 'Data'), $entry, $form->context(2024));
    $this->assertSame(152.6, $data['rows'][0]['synthetic']);
    $this->assertNull($data['rows'][1]['synthetic']);
    $this->assertSame(252.6, $data['rows'][2]['synthetic']);
    $payload = $form->prepare($entry, $data['rows']);
    $this->assertSame(252.6, $payload[2]['synthetic']);
    $results = $form->validateRules($rules, $data['rows'][0], null, 3, 'Data');
    $this->assertCount(4, $results);

    $record = [
      'shape' => 'record',
      'fields' => [
        'synthetic' => ['type' => 'synthetic', 'callback' => 'syntheticTotal'],
        'num' => ['cell' => 'B3', 'type' => 'abs'],
        'avg' => ['value' => '2'],
      ],
    ];
    $data = $form->read($form->sheet($workbook, 'Summary'), $record, $form->context(2024));
    $this->assertSame(2944702.0, $data['rows'][0]['synthetic']);
  }

  public function testSyntheticFieldCanUseCustomValidators(): void {
    $form = $this->form();
    $entry = $this->tableEntry();
    $entry['fields']['synthetic'] = [
      'type' => 'synthetic', 'callback' => 'syntheticTotal',
      'validators' => ['validateLessThanAverage'], 'warn_only' => ['validators'],
    ];
    $data = $form->read($form->sheet($this->workbookFixture(), 'Data'), $entry, $form->context(2024));
    $results = $form->validateRules($form->rules($entry), $data['rows'][0], null, 3, 'Data');
    $warnings = static::messages($results, 'warning');
    $this->assertCount(1, $warnings);
    $this->assertStringContainsString('Data!row 3 (synthetic = 152.6)', $warnings[0]);
  }

  #[DataProvider('customValidationFormProvider')]
  public function testCustomValidationHonorsGlobalAndLocalWarnings(bool $global_warning, bool $local_warning, bool $invalid, bool $blocked, bool $skip = false): void {
    $path = tempnam(sys_get_temp_dir(), 'ssot-validator-');
    IOFactory::createWriter($this->workbookFixture(), 'Xlsx')->save($path);
    $state = new FormState();
    try {
      $file = $this->createMock(FileInterface::class);
      $file->method('getFileUri')->willReturn('private://test.xlsx');
      $file->method('id')->willReturn(1);
      $file_storage = $this->createMock(EntityStorageInterface::class);
      $file_storage->method('load')->willReturn($file);
      $manager = $this->createMock(EntityTypeManagerInterface::class);
      $manager->method('getStorage')->willReturn($file_storage);
      $repository = $this->createMock(EntityTypeRepositoryInterface::class);
      $repository->method('getEntityTypeFromClass')->willReturn('file');
      $filesystem = $this->createMock(FileSystemInterface::class);
      $filesystem->method('realpath')->willReturn($path);
      $requests = new RequestStack();
      $requests->push(new Request());
      $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
      $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
      $messenger = $this->createMock(MessengerInterface::class);
      $messages = [];
      $messenger->method('addMessage')->willReturnCallback(function ($message, $type = 'status') use (&$messages) {
        $messages[] = [(string) $message, $type];
        return $this->createMock(MessengerInterface::class);
      });
      $container = \Drupal::getContainer();
      foreach ([
        'entity_type.manager' => $manager,
        'entity_type.repository' => $repository,
        'file_system' => $filesystem,
        'request_stack' => $requests,
        'logger.factory' => $logger_factory,
        'messenger' => $messenger,
      ] as $id => $service) {
        $container->set($id, $service);
      }
      $validator = $invalid
        ? fn() => ['success' => 'no', 'explanation' => 'Invalid result']
        : ($skip ? fn() => ['success' => false, 'action' => 'skip'] : fn() => ['success' => false, 'explanation' => 'Custom failure']);
      $entry = [
        'sheet' => 'Summary', 'shape' => 'record',
        'fields' => ['total' => [
          'cell' => 'B3', 'type' => $global_warning ? 'pct' : 'abs', 'validators' => ['check' => $validator],
          'warn_only' => $local_warning ? ['check'] : [],
        ]],
      ];
      $form = $this->form(['example' => $entry]);
      $state->setValue('source_file', [1]);
      $state->setValue('timestamp', new \DateTimeImmutable('2024-01-01'));
      $state->setValue('year', 2024);
      $state->setValue('warn_only', $global_warning);
      $form_array = [];
      $form->validateForm($form_array, $state);
      $this->assertSame($blocked, isset($state->getErrors()['source_file']));
      if (!$blocked) {
        $form->confirmationDescription($state->get('datasets'), $state->get('context'));
        $this->assertSame('warning', $messages[0][1]);
        $this->assertStringContainsString($skip ? 'No explanation given.' : 'Custom failure', $messages[0][0]);
        if ($skip) {
          $this->assertCount(1, $messages);
        }
      }
    }
    finally {
      $state->clearErrors();
      unlink($path);
    }
  }

  public static function customValidationFormProvider(): array {
    return [
      'error blocks' => [false, false, false, true],
      'local warning allows' => [false, true, false, false],
      'global warning allows' => [true, false, false, false],
      'invalid result still blocks globally' => [true, false, true, true],
      'unexplained skip still blocks' => [false, false, false, true, true],
      'local warning skip allows' => [false, true, false, false, true],
      'global warning skip allows' => [true, false, false, false, true],
    ];
  }

  #[DataProvider('invalidSyntheticDefinitionProvider')]
  public function testInvalidSyntheticDefinitionsFail(array $rule): void {
    $entry = $this->tableEntry(['fields' => [
      'synthetic' => $rule,
      'region' => ['type' => 'region'],
      'num' => ['type' => 'abs'],
      'pct' => ['type' => 'pct'],
      'avg' => ['type' => 'pct'],
    ]]);
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Invalid synthetic field definition for synthetic');
    $this->form()->rules($entry);
  }

  public static function invalidSyntheticDefinitionProvider(): array {
    return [
      'missing callback' => [['type' => 'synthetic']],
      'unknown callback' => [['type' => 'synthetic', 'callback' => 'doesNotExist']],
      'non-string callback' => [['type' => 'synthetic', 'callback' => 42]],
      'cell conflicts' => [['type' => 'synthetic', 'callback' => 'syntheticTotal', 'cell' => 'A']],
      'value conflicts' => [['type' => 'synthetic', 'callback' => 'syntheticTotal', 'value' => '1']],
      'validation conflicts' => [['type' => 'synthetic', 'callback' => 'syntheticTotal', 'equal' => 'num']],
    ];
  }

  public function testSyntheticCallbackCannotReturnInvalidPayloadValue(): void {
    $form = $this->form();
    $entry = $this->tableEntry();
    $entry['fields']['synthetic'] = ['type' => 'synthetic', 'callback' => 'invalidSyntheticValue'];
    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('Invalid synthetic value for synthetic');
    $form->read($form->sheet($this->workbookFixture(), 'Data'), $entry, $form->context(2024));
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
    $this->assertStringContainsString('Data!B4 has a warning', static::messages($out['regional']['results'], 'warning')[0]);
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
    $types = ['abs', 'num', 'pct', 'chg_abs', 'chg_pct', 'industry', 'region', 'synthetic', 'ignore'];
    $rule_keys = ['cell', 'value', 'type', 'callback', 'validators', 'exceptions', 'same_sign', 'equal', 'sum', 'crosscheck_value', 'crosscheck_change_abs', 'crosscheck_change_pct', 'warn_only'];

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
        foreach ($rule['validators'] ?? [] as $validator) {
          if (is_array($validator) && ($validator['callback'] ?? null) === 'validateBlankIf') {
            $options = $validator['options'];
            $this->assertArrayHasKey($options['field'], $rules, "$endpoint.$field");
            $this->assertArrayHasKey('value', $options);
          }
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
